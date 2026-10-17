<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-project-members')]
#[Title('Přiřadit osoby k projektu')]
#[Description('Přiřadí osoby k projektu nebo je z něj odebere. Projekt vidí jen přiřazené osoby (a administrátoři) a jen ony mohou být řešiteli jeho úkolů a zmíněny přes @Jméno. Nově přiřazená osoba ve výchozím stavu nevidí žádný seznam úkolů – viditelnost nastav přes all_todolists nebo todolist_ids. Vrátí aktuální seznam osob projektu.')]
class UpdateProjectMembersTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'add_user_ids' => 'sometimes|array',
            'add_user_ids.*' => 'integer|distinct|exists:users,id',
            'all_todolists' => 'sometimes|boolean',
            'todolist_ids' => 'sometimes|array',
            'todolist_ids.*' => 'integer|distinct',
            'remove_user_ids' => 'sometimes|array',
            'remove_user_ids.*' => 'integer|distinct',
        ], [
            'project_id.exists' => 'Projekt s tímto ID neexistuje. Seznam získáš nástrojem list-projects.',
            'add_user_ids.*.exists' => 'Uživatel s tímto ID v CRM neexistuje. Seznam získáš nástrojem list-users.',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if (! $this->canSee($user, $project)) {
            return $this->projectHidden();
        }

        $add = $validated['add_user_ids'] ?? [];
        $remove = $validated['remove_user_ids'] ?? [];

        if ($add === [] && $remove === []) {
            return Response::error('Uveď add_user_ids (koho přiřadit) nebo remove_user_ids (koho odebrat).');
        }

        if (array_intersect($add, $remove) !== []) {
            return Response::error('Stejná osoba nemůže být zároveň v add_user_ids i remove_user_ids.');
        }

        $todolistIds = $validated['todolist_ids'] ?? [];
        $foreign = array_diff($todolistIds, $project->todolists()->visibleTo($user)->pluck('todolists.id')->all());

        if ($foreign !== []) {
            return Response::error('Seznamy úkolů s ID '.implode(', ', $foreign).' v tomto projektu neexistují. Platná ID vypíše get-project.');
        }

        if ($add === [] && ($todolistIds !== [] || isset($validated['all_todolists']))) {
            return Response::error('Viditelnost seznamů (all_todolists, todolist_ids) se nastavuje osobám z add_user_ids – uveď je tam.');
        }

        DB::transaction(function () use ($project, $add, $remove, $validated, $todolistIds, $user) {
            $grantable = $project->todolists()->visibleTo($user)->pluck('todolists.id');

            foreach (User::whereIn('id', $add)->get() as $person) {
                $existing = $project->members()->whereKey($person->id)->first();

                // Without an explicit choice a new person sees no list and a current member keeps what they had.
                $project->grantAccess(
                    $person,
                    (bool) ($validated['all_todolists'] ?? $existing?->pivot->all_todolists ?? false),
                    $todolistIds,
                    isset($validated['todolist_ids']) || isset($validated['all_todolists']) ? $grantable : [],
                );
            }

            $project->members()->whereIn('users.id', $remove)->get()->each(fn ($member) => $project->revokeAccess($member));
        });

        $members = $project->members()->get(['users.id', 'users.name']);

        return Response::text(sprintf(
            "Osoby projektu \"%s\" byly upraveny (project_id %d).\n%s\nDetail v CRM: %s",
            $project->name,
            $project->id,
            json_encode([
                'members' => $members->map(fn ($member) => [
                    'user_id' => $member->id,
                    'user_name' => $member->name,
                ])->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            route('projects.show', $project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->description('ID projektu (list-projects).')
                ->required(),

            'add_user_ids' => $schema->array()
                ->description('ID osob, které se k projektu přiřadí. Už přiřazené osoby zůstanou beze změny. ID zjistíš nástrojem list-users.')
                ->items($schema->integer()),

            'all_todolists' => $schema->boolean()
                ->description('Má mít přiřazená osoba přístup ke všem seznamům úkolů projektu? Výchozí: ne (nevidí žádný seznam).'),

            'todolist_ids' => $schema->array()
                ->description('ID seznamů úkolů, které osoby z add_user_ids uvidí (platí, když all_todolists není true). ID vypíše get-project.')
                ->items($schema->integer()),

            'remove_user_ids' => $schema->array()
                ->description('ID osob, které se z projektu odeberou. Odebraná osoba (pokud není administrátor) projekt přestane vidět; úkoly, které řeší, jí zůstanou přiřazené.')
                ->items($schema->integer()),
        ];
    }
}
