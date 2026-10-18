<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\TodoComment;
use App\Models\Todolist;
use App\Models\User;
use App\Notifications\TodoActivity;
use App\Support\ActivityLog;
use App\Support\TimeVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TodoController extends Controller
{
    private const MESSAGES = [
        'assigned_user_id.exists' => 'Řešitelem může být jen osoba přiřazená k projektu.',
    ];

    public function store(Request $request, Todolist $todolist)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|integer|exists:todos,id',
            'assigned_user_id' => ['nullable', $todolist->project->memberRule()],
            'due_date' => 'nullable|date',
            ...Todo::detailRules(),
            ...self::labelRules(),
        ], self::MESSAGES);

        // A customer only describes what is needed; estimates, people, labels and structure are for the team.
        if ($request->user()->isCustomer()) {
            $validated = Arr::only($validated, ['name', 'description', 'due_date']);
        }

        // A parent from another list would produce a todo that renders nowhere.
        if (! empty($validated['parent_id'])
            && ! $todolist->todos()->whereKey($validated['parent_id'])->exists()) {
            return back()->withErrors(['parent_id' => 'Nadřazený úkol nepatří do tohoto seznamu.']);
        }

        $todo = $todolist->todos()->create([
            ...collect($validated)->except('label_ids')->all(),
            'days' => $validated['days'] ?? 0,
            'created_by_user_id' => $request->user()->id,
            'sort_order' => $todolist->todos()->max('sort_order') + 1,
        ]);

        if (! empty($validated['label_ids'])) {
            $todo->labels()->sync($validated['label_ids']);
        }

        self::notifyAssignee($todo, $request->user());

        return back()->with('success', 'Úkol byl přidán.');
    }

    public function show(Request $request, Todo $todo)
    {
        $todo->load([
            'todolist.project.userRates:id,name',
            'todolist.project.members:id,name,avatar',
            'parent:id,name',
            'children' => fn ($query) => $query->withCount('children'),
            'children.assignee:id,name',
            'children.labels',
            'assignee:id,name',
            'creator:id,name,avatar',
            'completer:id,name',
            'labels',
            'watchers:id,name',
            'attachments',
            'workReports.user:id,name',
            'workReports.invoice:id,number,url',
            'comments.user:id,name,avatar',
            'comments.attachments',
        ]);

        TimeVisibility::applyToTodos([$todo], $request->user());
        TimeVisibility::applyToProject($todo->todolist->project, $request->user());

        return inertia('Todos/Show', [
            'todo' => $todo,
            // Outside administration only the project's own people are listed.
            'users' => $request->user()->isStaff()
                ? User::orderBy('name')->get(['id', 'name'])
                : $todo->todolist->project->members->map->only(['id', 'name'])->values(),
            'labels' => Label::orderBy('name')->get(),
            // Candidates for moving the todo under another one of the same list.
            'listTodos' => $todo->todolist->todos()->get(['id', 'parent_id', 'name']),
        ]);
    }

    public function update(Request $request, Todo $todo)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'sometimes|integer|min:0',
            'is_done' => 'sometimes|boolean',
            'assigned_user_id' => ['nullable', $todo->todolist->project->memberRule()],
            'due_date' => 'nullable|date',
            'sort_order' => 'sometimes|integer|min:0',
            'parent_id' => 'sometimes|nullable|integer',
            ...Todo::detailRules(),
            ...Todo::recurrenceRules(),
            ...self::labelRules(),
            'watcher_ids' => 'sometimes|nullable|array',
            'watcher_ids.*' => ['integer', 'distinct', $todo->todolist->project->memberRule()],
            // Description files; sent together with the description from its editor.
            'files' => 'sometimes|array|max:20',
            'files.*' => 'file|max:'.TodoComment::MAX_FILE_KILOBYTES,
            'remove_attachment_ids' => 'sometimes|array',
            'remove_attachment_ids.*' => 'integer',
        ], self::MESSAGES);

        $files = $request->file('files', []);
        $removeIds = $validated['remove_attachment_ids'] ?? [];
        unset($validated['files'], $validated['remove_attachment_ids']);

        if (array_key_exists('parent_id', $validated)) {
            $todo->moveUnder($validated['parent_id']);
            unset($validated['parent_id']);
        }

        $validated = $todo->prepareRecurrence($validated);

        $previousAssigneeId = $todo->assigned_user_id;
        $wasDone = $todo->is_done;

        if ($request->has('is_done')) {
            $validated = [...$validated, ...Todo::completionAttributes($request->boolean('is_done'), $request->user()->id)];
        }

        DB::transaction(function () use ($todo, $validated, $files, $removeIds) {
            $todo->update(collect($validated)->except(['label_ids', 'watcher_ids'])->all());

            if (array_key_exists('watcher_ids', $validated)) {
                $todo->watchers()->sync($validated['watcher_ids'] ?? []);
            }

            if (array_key_exists('label_ids', $validated)) {
                $labelChanges = $todo->labels()->sync($validated['label_ids'] ?? []);

                if ($labelChanges['attached'] !== [] || $labelChanges['detached'] !== []) {
                    ActivityLog::noteTodoField($todo, 'labels');
                }
            }

            $todo->attachments()->whereIn('id', $removeIds)->get()->each->delete();

            foreach ($files as $file) {
                TodoAttachment::storeFor($todo, $file);
            }
        });

        if ($todo->assigned_user_id !== $previousAssigneeId) {
            self::notifyAssignee($todo, $request->user());
        }

        if (! $wasDone && $todo->is_done) {
            self::notifyWatchers($todo, $request->user(), TodoActivity::COMPLETED);
        }

        return back()->with('success', 'Úkol byl upraven.');
    }

    /**
     * Serves a description attachment from the private disk; images and PDFs open in the browser.
     */
    public function attachment(Request $request, TodoAttachment $attachment)
    {
        return $attachment->toResponse($request->boolean('download'));
    }

    public function destroy(Request $request, Todo $todo)
    {
        $project = $todo->todolist->project;
        $referer = $request->headers->get('referer');

        $todo->delete();

        // A subtask removed on its parent's detail stays there; the deleted todo's own page is gone.
        if ($referer && $referer !== route('todos.show', $todo)) {
            return back()->with('success', 'Úkol byl smazán.');
        }

        return redirect()->route('projects.show', $project)->with('success', 'Úkol byl smazán.');
    }

    /**
     * The person a todo was handed to hears about it, unless they did it themselves.
     */
    private static function notifyAssignee(Todo $todo, User $actor): void
    {
        if ($todo->assigned_user_id && $todo->assigned_user_id !== $actor->id) {
            $todo->assignee?->notify(new TodoActivity($todo, $actor, TodoActivity::ASSIGNED));
        }
    }

    /**
     * Followers hear about what happened, except the person who did it and the assignee (who is told separately).
     */
    public static function notifyWatchers(Todo $todo, User $actor, string $type): void
    {
        $todo->watchers()->whereKeyNot($actor->id)->get()
            ->each(fn (User $watcher) => $watcher->notify(new TodoActivity($todo, $actor, $type)));
    }

    /**
     * @return array<string, string>
     */
    private static function labelRules(): array
    {
        return [
            'label_ids' => 'sometimes|nullable|array',
            'label_ids.*' => 'integer|distinct|exists:labels,id',
        ];
    }
}
