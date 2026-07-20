<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-project')]
#[Title('Upravit projekt')]
#[Description('Upraví existující projekt. Vyplň jen pole, která se mají změnit – ostatní zůstanou beze změny.')]
class UpdateProjectTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:projects,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'company_id' => 'sometimes|nullable|integer|exists:companies,id',
            'company_employee_id' => 'sometimes|nullable|integer|exists:company_employees,id',
            'status' => 'sometimes|string|in:active,on_hold,done,archived',
        ], [
            'id.exists' => 'Projekt s tímto ID neexistuje. Seznam získáš nástrojem list-projects.',
            'status.in' => 'Stav projektu musí být "active", "on_hold", "done" nebo "archived".',
        ]);

        $project = Project::findOrFail($validated['id']);
        unset($validated['id']);

        if ($validated === []) {
            return Response::error('Neuvedl jsi žádnou změnu. Vyplň alespoň jedno pole, které se má upravit.');
        }

        $project->update($validated);

        return Response::text(sprintf(
            "Projekt \"%s\" byl upraven (project_id %d, stav %s).\nZměněná pole: %s\nDetail v CRM: %s",
            $project->name,
            $project->id,
            $project->status,
            implode(', ', array_keys($validated)),
            route('projects.show', $project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravovaného projektu.')
                ->required(),

            'name' => $schema->string()
                ->description('Nový název projektu.'),

            'description' => $schema->string()
                ->description('Nový popis projektu.'),

            'company_id' => $schema->integer()
                ->description('Nové ID firmy, které projekt patří.'),

            'company_employee_id' => $schema->integer()
                ->description('Nové ID kontaktní osoby.'),

            'status' => $schema->string()
                ->enum(['active', 'on_hold', 'done', 'archived'])
                ->description('Nový stav projektu.'),
        ];
    }
}
