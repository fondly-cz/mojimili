<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\Todolist;
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
        ]);

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
        $todo->delete();

        return back()->with('success', 'Úkol byl smazán.');
    }
}
