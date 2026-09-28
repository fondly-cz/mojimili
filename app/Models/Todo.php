<?php

namespace App\Models;

use App\Actions\SpawnNextRecurringTodo;
use App\Enums\RecurrenceFrequency;
use App\Support\RichText;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_done' => 'boolean',
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'days' => 'integer',
        'parent_id' => 'integer',
        'recurrence_frequency' => RecurrenceFrequency::class,
        'recurrence_interval' => 'integer',
        'recurrence_working_days_only' => 'boolean',
        'recurrence_ends_on' => 'date',
        'recurrence_remaining' => 'integer',
        'recurrence_copy_description' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updated(function (Todo $todo) {
            if ($todo->wasChanged('is_done') && $todo->is_done) {
                app(SpawnNextRecurringTodo::class)->handle($todo);
            }
        });

        // Thread rows cascade in the database; the attachment files have to go explicitly.
        static::deleted(function (Todo $todo) {
            TodoComment::purgeFilesFor([$todo->id]);
        });
    }

    /**
     * The description is written in the rich editor and rendered as HTML; plain text
     * and Markdown (e.g. from the MCP tools) are converted, everything is sanitized.
     */
    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = RichText::toHtml($value);
    }

    /**
     * Validation rules shared by the web UI and the MCP tool.
     *
     * @return array<string, mixed>
     */
    public static function recurrenceRules(): array
    {
        return [
            'recurrence_frequency' => ['sometimes', 'nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => 'sometimes|integer|min:1|max:365',
            'recurrence_working_days_only' => 'sometimes|boolean',
            'recurrence_ends_on' => 'sometimes|nullable|date',
            'recurrence_remaining' => 'sometimes|nullable|integer|min:0|max:1000',
            'recurrence_copy_description' => 'sometimes|boolean',
        ];
    }

    /**
     * Checks recurrence changes before an update and anchors a new recurrence on a due date.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function prepareRecurrence(array $attributes): array
    {
        if (empty($attributes['recurrence_frequency'])) {
            return $attributes;
        }

        if ($this->hasRecurringRelative()) {
            throw ValidationException::withMessages([
                'recurrence_frequency' => 'Opakování nelze nastavit zároveň na úkolu a jeho nadřazeném úkolu nebo podúkolu.',
            ]);
        }

        // The due date is the anchor the next occurrences are counted from.
        if (! array_key_exists('due_date', $attributes) || $attributes['due_date'] === null) {
            $attributes['due_date'] = $this->due_date ?? today();
        }

        return $attributes;
    }

    /**
     * Freelo forbids recurrence on both a todo and its subtask; the copies would multiply.
     */
    public function hasRecurringRelative(): bool
    {
        for ($parent = $this->parent; $parent; $parent = $parent->parent) {
            if ($parent->recurrence_frequency) {
                return true;
            }
        }

        return $this->hasRecurringDescendant($this);
    }

    private function hasRecurringDescendant(Todo $todo): bool
    {
        foreach ($todo->children()->get() as $child) {
            if ($child->recurrence_frequency || $this->hasRecurringDescendant($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Human readable summary, e.g. "Týdně (každý 2.)".
     */
    public function recurrenceLabel(): ?string
    {
        if (! $this->recurrence_frequency) {
            return null;
        }

        $label = $this->recurrence_interval > 1
            ? sprintf('%s (každý %d.)', $this->recurrence_frequency->label(), $this->recurrence_interval)
            : $this->recurrence_frequency->label();

        if ($this->recurrence_working_days_only && $this->recurrence_frequency === RecurrenceFrequency::DAILY) {
            $label .= ', jen pracovní dny';
        }

        return $label;
    }

    /**
     * @return BelongsTo<Todolist, $this>
     */
    public function todolist(): BelongsTo
    {
        return $this->belongsTo(Todolist::class);
    }

    /**
     * @return BelongsTo<CalculationItem, $this>
     */
    public function calculationItem(): BelongsTo
    {
        return $this->belongsTo(CalculationItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Todo::class, 'parent_id');
    }

    /**
     * @return HasMany<Todo, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Todo::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<WorkReport, $this>
     */
    public function workReports(): HasMany
    {
        return $this->hasMany(WorkReport::class)->orderBy('date')->orderBy('id');
    }

    /**
     * @return HasMany<TodoComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TodoComment::class)->orderBy('created_at')->orderBy('id');
    }
}
