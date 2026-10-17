<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Todolist;
use App\Support\TimeVisibility;
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

        $todolist->shareWith($request->user());

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

    public function show(Request $request, Todolist $todolist)
    {
        $todolist->load([
            'project:id,name,status,company_id',
            'project.members:id,name,avatar',
            'calculation:id,customer_name,customer_company',
            'todos' => fn ($query) => $query->withCount('comments'),
            'todos.assignee:id,name',
            'todos.labels',
            'todos.workReports.user:id,name',
            'todos.workReports.invoice:id,number,url',
        ]);

        $user = $request->user();
        $reportedMinutes = $user->isStaff() ? (int) $todolist->todos->flatMap->workReports->sum('minutes') : null;

        TimeVisibility::applyToTodos($todolist->todos, $user);

        return inertia('Todolists/Show', [
            'todolist' => $todolist,
            'reportedMinutes' => $reportedMinutes,
        ]);
    }

    public function archive(Todolist $todolist)
    {
        $todolist->update(['archived_at' => now()]);

        return back()->with('success', 'Seznam úkolů byl archivován.');
    }

    public function unarchive(Todolist $todolist)
    {
        $todolist->update(['archived_at' => null]);

        return back()->with('success', 'Seznam úkolů byl obnoven z archivu.');
    }

    public function destroy(Request $request, Todolist $todolist)
    {
        $validated = $request->validate(['delete_work_reports' => 'sometimes|boolean']);
        $project = $todolist->project;
        $referer = $request->headers->get('referer');

        $todolist->deleteWithReports((bool) ($validated['delete_work_reports'] ?? false));

        // From the list's own page there is nothing left to go back to.
        if ($referer && str_contains($referer, "/todolists/{$todolist->id}")) {
            return redirect()->route('projects.show', $project)->with('success', 'Seznam úkolů byl smazán.');
        }

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
