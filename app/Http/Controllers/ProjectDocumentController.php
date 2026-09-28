<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;

class ProjectDocumentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate(ProjectDocument::rules());

        $project->documents()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'sort_order' => $project->documents()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Dokument byl přidán.');
    }

    public function update(Request $request, ProjectDocument $document)
    {
        $document->update($request->validate(ProjectDocument::rules(partial: true)));

        return back()->with('success', 'Dokument byl upraven.');
    }

    public function destroy(ProjectDocument $document)
    {
        $document->delete();

        return back()->with('success', 'Dokument byl smazán.');
    }
}
