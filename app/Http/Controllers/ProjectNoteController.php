<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate(ProjectNote::rules());

        $project->notes()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'sort_order' => $project->notes()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Poznámka byla přidána.');
    }

    public function update(Request $request, ProjectNote $note)
    {
        $note->update($request->validate(ProjectNote::rules(partial: true)));

        return back()->with('success', 'Poznámka byla upravena.');
    }

    public function destroy(ProjectNote $note)
    {
        $note->delete();

        return back()->with('success', 'Poznámka byla smazána.');
    }
}
