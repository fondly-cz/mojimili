<?php

namespace App\Support;

use App\Models\Calculation;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectNote;
use App\Models\Service;
use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\TodoComment;
use App\Models\TodoCommentAttachment;
use App\Models\Todolist;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes the change history. Every create, update and delete of the tracked records is logged
 * from model events, so the web UI, the MCP tools and the importers are all covered. Views are not.
 */
class ActivityLog
{
    /** @var array<class-string<Model>, string> */
    private const SUBJECTS = [
        Project::class => 'project',
        Todolist::class => 'todolist',
        Todo::class => 'todo',
        TodoComment::class => 'comment',
        WorkReport::class => 'work_report',
        ProjectDocument::class => 'document',
        ProjectNote::class => 'note',
        TodoAttachment::class => 'todo_attachment',
        TodoCommentAttachment::class => 'comment_attachment',
        Invoice::class => 'invoice',
        Calculation::class => 'calculation',
        Company::class => 'company',
        CompanyEmployee::class => 'contact',
        Service::class => 'service',
        User::class => 'user',
    ];

    /** Changes that are noise or secrets and never worth a log entry. */
    private const IGNORED_FIELDS = [
        'updated_at', 'created_at', 'sort_order', 'password', 'remember_token', 'google_id', 'avatar',
        'completed_at', 'completed_by_user_id', 'recurrence_remaining', 'email_verified_at',
    ];

    /** Long content is logged as "changed", never copied. */
    private const BULK_FIELDS = ['description', 'body', 'content'];

    public static function register(): void
    {
        foreach (array_keys(self::SUBJECTS) as $class) {
            $class::created(fn (Model $model) => self::record($model, 'created'));
            $class::updated(fn (Model $model) => self::record($model, 'updated'));
            $class::deleted(fn (Model $model) => self::record($model, 'deleted'));
        }
    }

    /**
     * Log something that is not a plain model change, e.g. a person being added to a project.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function note(string $event, Project $project, string $label, array $properties = [], ?int $todolistId = null): void
    {
        self::write($event, 'project', $project->id, $label, $properties, $project->id, $todolistId, null);
    }

    /**
     * A change to a todo that is not a column of its own, e.g. its labels.
     */
    public static function noteTodoField(Todo $todo, string $field): void
    {
        self::write('todo.updated', 'todo', $todo->id, $todo->name, ['changes' => [$field => []]], $todo->todolist?->project_id, $todo->todolist_id, $todo->id);
    }

    private static function record(Model $model, string $action): void
    {
        $subject = self::SUBJECTS[$model::class];
        [$projectId, $todolistId, $todoId] = self::scope($model);

        if ($action === 'updated') {
            foreach (self::updateEvents($model, $subject) as [$event, $properties]) {
                self::write($event, $subject, $model->getKey(), self::label($model), $properties, $projectId, $todolistId, $todoId);
            }

            return;
        }

        self::write("{$subject}.{$action}", $subject, $model->getKey(), self::label($model), self::details($model, $action), $projectId, $todolistId, $todoId);
    }

    /**
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private static function updateEvents(Model $model, string $subject): array
    {
        $changes = array_diff_key($model->getChanges(), array_flip(self::IGNORED_FIELDS));
        $events = [];

        if ($model instanceof Todo && $model->wasChanged('is_done')) {
            $events[] = [$model->is_done ? 'todo.completed' : 'todo.reopened', []];
            unset($changes['is_done']);
        }

        if ($model instanceof Todolist && array_key_exists('archived_at', $changes)) {
            $events[] = [$changes['archived_at'] ? 'todolist.archived' : 'todolist.unarchived', []];
            unset($changes['archived_at']);
        }

        if ($changes !== []) {
            $events[] = ["{$subject}.updated", ['changes' => self::describeChanges($model, $changes)]];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, array{from?: mixed, to?: mixed}>
     */
    private static function describeChanges(Model $model, array $changes): array
    {
        $described = [];

        foreach ($changes as $field => $new) {
            if (in_array($field, self::BULK_FIELDS, true)) {
                $described[$field] = [];

                continue;
            }

            $old = $model->getOriginal($field);

            if ($field === 'assigned_user_id' || $field === 'user_id') {
                $names = User::whereIn('id', array_filter([$old, $new]))->pluck('name', 'id');
                [$old, $new] = [$names[$old] ?? null, $names[$new] ?? null];
            }

            $described[$field] = ['from' => self::short($old), 'to' => self::short($new)];
        }

        return $described;
    }

    private static function short(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_scalar($value) || $value === null) {
            return is_string($value) ? mb_strimwidth($value, 0, 120, '…') : $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function details(Model $model, string $action): array
    {
        if ($model instanceof WorkReport) {
            return [
                'minutes' => $model->minutes,
                'date' => $model->date?->toDateString(),
                'person' => $model->user?->name,
                'todo' => $model->todo?->name ?? $model->todo_name,
            ];
        }

        if ($model instanceof Todo) {
            return ['todolist' => $model->todolist?->name, 'subtask' => $model->parent_id !== null];
        }

        if ($model instanceof TodoComment || $model instanceof TodoAttachment) {
            return ['todo' => $model->todo?->name];
        }

        if ($model instanceof TodoCommentAttachment) {
            return ['todo' => $model->comment?->todo?->name];
        }

        if ($model instanceof Todolist && $action === 'deleted') {
            return ['todos' => $model->todos()->count()];
        }

        return [];
    }

    /**
     * The project, todolist and todo a record belongs to, for filtering and visibility.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int}
     */
    private static function scope(Model $model): array
    {
        return match (true) {
            $model instanceof Project => [$model->id, null, null],
            $model instanceof Todolist => [$model->project_id, $model->id, null],
            $model instanceof Todo => [$model->todolist?->project_id, $model->todolist_id, $model->id],
            $model instanceof TodoComment, $model instanceof TodoAttachment => [$model->todo?->todolist?->project_id, $model->todo?->todolist_id, $model->todo_id],
            $model instanceof TodoCommentAttachment => self::scope($model->comment ?? new TodoComment),
            $model instanceof WorkReport => [$model->project_id ?? $model->todo?->todolist?->project_id, $model->todo?->todolist_id, $model->todo_id],
            $model instanceof ProjectDocument, $model instanceof ProjectNote => [$model->project_id, null, null],
            default => [null, null, null],
        };
    }

    private static function label(Model $model): ?string
    {
        $label = match (true) {
            $model instanceof WorkReport => $model->todo?->name ?? $model->todo_name,
            $model instanceof TodoComment => $model->todo?->name,
            $model instanceof Invoice => $model->number,
            $model instanceof Calculation => $model->customer_company ?: $model->customer_name,
            $model instanceof TodoAttachment, $model instanceof TodoCommentAttachment => $model->original_name,
            default => $model->name ?? null,
        };

        return $label === null ? null : mb_strimwidth((string) $label, 0, 255, '…');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function write(string $event, string $subject, mixed $subjectId, ?string $label, array $properties, ?int $projectId, ?int $todolistId, ?int $todoId): void
    {
        DB::table('activities')->insert([
            'user_id' => Auth::id(),
            'project_id' => $projectId,
            'todolist_id' => $todolistId,
            'todo_id' => $todoId,
            'event' => $event,
            'subject_type' => $subject,
            'subject_id' => $subjectId,
            'subject_label' => $label,
            'properties' => $properties === [] ? null : json_encode($properties, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
