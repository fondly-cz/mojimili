<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Http\Request;

class TodoController extends Controller
{
    public function store(Request $request, Todolist $todolist)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|integer|exists:todos,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            ...Todo::detailRules(),
            ...self::labelRules(),
        ]);

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

        return back()->with('success', 'Úkol byl přidán.');
    }

    public function show(Todo $todo)
    {
        $todo->load([
            'todolist.project.userRates:id,name',
            'parent:id,name',
            'children:id,todolist_id,parent_id,name,is_done',
            'assignee:id,name',
            'creator:id,name',
            'completer:id,name',
            'labels',
            'workReports.user:id,name',
            'workReports.invoice:id,number,url',
            'comments.user:id,name',
            'comments.attachments',
        ]);

        return inertia('Todos/Show', [
            'todo' => $todo,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'labels' => Label::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Todo $todo)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'sometimes|integer|min:0',
            'is_done' => 'sometimes|boolean',
            'assigned_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'sort_order' => 'sometimes|integer|min:0',
            ...Todo::detailRules(),
            ...Todo::recurrenceRules(),
            ...self::labelRules(),
        ]);

        $validated = $todo->prepareRecurrence($validated);

        if ($request->has('is_done')) {
            $validated = [...$validated, ...Todo::completionAttributes($request->boolean('is_done'), $request->user()->id)];
        }

        $todo->update(collect($validated)->except('label_ids')->all());

        if (array_key_exists('label_ids', $validated)) {
            $todo->labels()->sync($validated['label_ids'] ?? []);
        }

        return back()->with('success', 'Úkol byl upraven.');
    }

    public function destroy(Todo $todo)
    {
        $project = $todo->todolist->project;

        $todo->delete();

        // Deleting from the todo's own detail page must not lead back to it.
        return redirect()->route('projects.show', $project)->with('success', 'Úkol byl smazán.');
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
