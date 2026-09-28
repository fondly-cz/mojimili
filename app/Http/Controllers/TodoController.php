<?php

namespace App\Http\Controllers;

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
        ]);

        // A parent from another list would produce a todo that renders nowhere.
        if (! empty($validated['parent_id'])
            && ! $todolist->todos()->whereKey($validated['parent_id'])->exists()) {
            return back()->withErrors(['parent_id' => 'Nadřazený úkol nepatří do tohoto seznamu.']);
        }

        $todolist->todos()->create([
            ...$validated,
            'days' => $validated['days'] ?? 0,
            'sort_order' => $todolist->todos()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Úkol byl přidán.');
    }

    public function show(Todo $todo)
    {
        $todo->load([
            'todolist.project.userRates:id,name',
            'parent:id,name',
            'children:id,todolist_id,parent_id,name,is_done',
            'assignee:id,name',
            'workReports.user:id,name',
            'workReports.invoice:id,number,url',
            'comments.user:id,name',
            'comments.attachments',
        ]);

        return inertia('Todos/Show', [
            'todo' => $todo,
            'users' => User::orderBy('name')->get(['id', 'name']),
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
            ...Todo::recurrenceRules(),
        ]);

        $validated = $todo->prepareRecurrence($validated);

        if ($request->has('is_done')) {
            $isDone = $request->boolean('is_done');
            $validated['is_done'] = $isDone;
            $validated['completed_at'] = $isDone ? now() : null;
        }

        $todo->update($validated);

        return back()->with('success', 'Úkol byl upraven.');
    }

    public function destroy(Todo $todo)
    {
        $project = $todo->todolist->project;

        $todo->delete();

        // Deleting from the todo's own detail page must not lead back to it.
        return redirect()->route('projects.show', $project)->with('success', 'Úkol byl smazán.');
    }
}
