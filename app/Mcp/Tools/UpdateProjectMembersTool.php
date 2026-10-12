<?php

namespace App\Mcp\Tools;

use App\Models\Project;
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
#[Description('Přiřadí osoby k projektu nebo je z něj odebere. Projekt vidí jen přiřazené osoby (a administrátoři) a jen ony mohou být řešiteli jeho úkolů a zmíněny přes @Jméno. Vrátí aktuální seznam osob projektu.')]
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

        DB::transaction(function () use ($project, $add, $remove) {
            $project->members()->syncWithoutDetaching($add);
            $project->members()->detach($remove);
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

            'remove_user_ids' => $schema->array()
                ->description('ID osob, které se z projektu odeberou. Odebraná osoba (pokud není administrátor) projekt přestane vidět; úkoly, které řeší, jí zůstanou přiřazené.')
                ->items($schema->integer()),
        ];
    }
}
