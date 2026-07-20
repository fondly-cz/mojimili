<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Todolist;
use Illuminate\Http\Request;

class TodolistController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'todos' => 'nullable|array',
            'todos.*.name' => 'required|string|max:255',
            'todos.*.description' => 'nullable|string',
            'todos.*.days' => 'nullable|integer|min:0',
        ]);

        $todolist = $project->todolists()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $project->todolists()->max('sort_order') + 1,
        ]);

        foreach ($validated['todos'] ?? [] as $index => $todo) {
            $todolist->todos()->create([
                'name' => $todo['name'],
                'description' => $todo['description'] ?? null,
                'days' => $todo['days'] ?? 0,
                'sort_order' => $index,
            ]);
        }

        return back()->with('success', 'Seznam úkolů byl vytvořen.');
    }

    public function update(Request $request, Todolist $todolist)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $todolist->update($validated);

        return back()->with('success', 'Seznam úkolů byl upraven.');
    }

    public function destroy(Todolist $todolist)
    {
        $todolist->delete();

        return back()->with('success', 'Seznam úkolů byl smazán.');
    }

    public function reorder(Request $request, Todolist $todolist)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:todos,id',
        ]);

        // Scope to this list so a crafted payload cannot reshuffle another list's todos.
        $todos = $todolist->todos()->whereIn('id', $validated['ids'])->get()->keyBy('id');

        foreach ($validated['ids'] as $index => $id) {
            $todos->get($id)?->update(['sort_order' => $index]);
        }

        return back()->with('success', 'Pořadí úkolů bylo uloženo.');
    }
}
