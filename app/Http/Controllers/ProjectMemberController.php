<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $project->members()->syncWithoutDetaching([$validated['user_id']]);

        return back()->with('success', 'Osoba byla přidána do projektu.');
    }

    public function destroy(Request $request, Project $project, User $user)
    {
        $project->members()->detach($user->id);

        // Whoever removes themselves no longer sees the project they came from.
        if (! $project->isVisibleTo($request->user())) {
            return redirect()->route('projects.index')->with('success', 'Z projektu jste se odebrali.');
        }

        return back()->with('success', 'Osoba byla z projektu odebrána.');
    }
}
