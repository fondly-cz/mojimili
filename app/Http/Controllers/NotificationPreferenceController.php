<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Channels through which the signed-in person receives notifications about todos.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'notify_in_app' => 'required|boolean',
            'notify_by_mail' => 'required|boolean',
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Nastavení notifikací bylo uloženo.');
    }

    /**
     * Follow (or stop following) everything that happens on a project's todos.
     */
    public function follow(Request $request, Project $project)
    {
        abort_unless($project->isVisibleTo($request->user()), 404);

        $validated = $request->validate(['follow' => 'required|boolean']);

        $project->setFollowedBy($request->user(), $validated['follow']);

        return back()->with('success', $validated['follow']
            ? 'Budete dostávat notifikace o všech úkolech projektu.'
            : 'Sledování projektu bylo vypnuto.');
    }
}
