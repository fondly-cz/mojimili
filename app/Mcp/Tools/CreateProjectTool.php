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

#[Name('create-project')]
#[Title('Založit projekt')]
#[Description('Založí nový projekt v CRM. Vrácené project_id použij v nástrojích create-todolist nebo create-todolist-from-calculation.')]
class CreateProjectTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'nullable|integer|exists:companies,id',
            'company_employee_id' => 'nullable|integer|exists:company_employees,id',
            'status' => 'nullable|string|in:active,on_hold,done,archived',
        ], [
            'status.in' => 'Stav projektu musí být "active", "on_hold", "done" nebo "archived".',
            'company_id.exists' => 'Firma s tímto ID neexistuje. Seznam získáš nástrojem list-companies.',
        ]);

        $project = Project::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
            'user_id' => $user->id,
        ]);

        return Response::text(sprintf(
            "Projekt \"%s\" byl založen (project_id %d, stav %s).\nDetail v CRM: %s",
            $project->name,
            $project->id,
            $project->status,
            route('projects.show', $project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Název projektu.')
                ->required(),

            'description' => $schema->string()
                ->description('Interní popis projektu.'),

            'company_id' => $schema->integer()
                ->description('ID firmy, které projekt patří (získáš z list-companies).'),

            'company_employee_id' => $schema->integer()
                ->description('ID kontaktní osoby ve firmě.'),

            'status' => $schema->string()
                ->enum(['active', 'on_hold', 'done', 'archived'])
                ->description('Stav projektu.')
                ->default('active'),
        ];
    }
}
