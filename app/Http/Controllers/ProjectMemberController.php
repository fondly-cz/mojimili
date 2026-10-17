<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Support\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectMemberController extends Controller
{
    /**
     * Add a person by id, or by e-mail: an existing account joins right away, anyone else
     * is invited and joins once they sign up with that address.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'user_id' => 'required_without:email|nullable|integer|exists:users,id',
            'email' => 'required_without:user_id|nullable|email:rfc|max:255',
            ...$this->accessRules(),
        ], [
            'email.required_without' => 'Vyberte osobu, nebo zadejte její e-mail.',
            'user_id.required_without' => 'Vyberte osobu, nebo zadejte její e-mail.',
        ]);

        $allTodolists = (bool) ($validated['all_todolists'] ?? false);
        $todolistIds = $validated['todolist_ids'] ?? [];
        $grantable = $this->grantableTodolistIds($request, $project);

        $user = isset($validated['user_id'])
            ? User::find($validated['user_id'])
            : User::whereRaw('lower(email) = ?', [mb_strtolower($validated['email'])])->first();

        if ($user) {
            $project->grantAccess($user, $allTodolists, $todolistIds, $grantable);

            return back()->with('success', 'Osoba byla přidána do projektu.');
        }

        $email = mb_strtolower($validated['email']);

        $invitation = ProjectInvitation::updateOrCreate(
            ['project_id' => $project->id, 'email' => $email],
            [
                'all_todolists' => $allTodolists,
                'todolist_ids' => $allTodolists ? [] : array_values(array_intersect($todolistIds, $grantable->all())),
                'invited_by_user_id' => $request->user()->id,
            ],
        );

        ActivityLog::note('project.invited', $project, $email, [
            'all_todolists' => $allTodolists,
            'todolists' => $allTodolists ? [] : $project->todolists()->whereIn('id', $invitation->todolist_ids)->pluck('name')->all(),
        ]);

        return back()->with('success', "Pozvánka pro {$email} je uložená. Do projektu se osoba připojí, jakmile se s tímto e-mailem zaregistruje.");
    }

    public function update(Request $request, Project $project, User $user)
    {
        $validated = $request->validate($this->accessRules());

        abort_unless($project->members()->whereKey($user->id)->exists(), 404);

        $project->grantAccess(
            $user,
            (bool) ($validated['all_todolists'] ?? false),
            $validated['todolist_ids'] ?? [],
            $this->grantableTodolistIds($request, $project),
        );

        return back()->with('success', 'Přístup osoby byl upraven.');
    }

    public function destroy(Request $request, Project $project, User $user)
    {
        DB::transaction(fn () => $project->revokeAccess($user));

        // Whoever removes themselves no longer sees the project they came from.
        if (! $project->isVisibleTo($request->user())) {
            return redirect()->route('projects.index')->with('success', 'Z projektu jste se odebrali.');
        }

        return back()->with('success', 'Osoba byla z projektu odebrána.');
    }

    public function destroyInvitation(Project $project, ProjectInvitation $invitation)
    {
        abort_unless($invitation->project_id === $project->id, 404);

        $invitation->delete();

        ActivityLog::note('project.invitation_cancelled', $project, $invitation->email);

        return back()->with('success', 'Pozvánka byla zrušena.');
    }

    /**
     * @return array<string, mixed>
     */
    private function accessRules(): array
    {
        return [
            'all_todolists' => 'sometimes|boolean',
            'todolist_ids' => 'sometimes|array',
            'todolist_ids.*' => 'integer',
        ];
    }

    /**
     * Only the lists the acting person sees can be handed to somebody else.
     *
     * @return Collection<int, int>
     */
    private function grantableTodolistIds(Request $request, Project $project)
    {
        return $project->todolists()->visibleTo($request->user())->pluck('todolists.id');
    }
}
