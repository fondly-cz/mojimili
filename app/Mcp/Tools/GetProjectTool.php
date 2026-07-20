<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('get-project')]
#[Title('Detail projektu')]
#[Description('Vrátí detail projektu včetně všech seznamů úkolů a jednotlivých úkolů, jejich zanoření (parent_id), termínů a stavu dokončení.')]
class GetProjectTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:projects,id',
        ], [
            'id.exists' => 'Projekt s tímto ID neexistuje. Seznam získáš nástrojem list-projects.',
        ]);

        $project = Project::with([
            'company:id,name',
            'todolists.todos.assignee:id,name',
        ])->where('id', $validated['id'])->firstOrFail();

        return Response::text(collect([
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'company_id' => $project->company_id,
            'company_name' => $project->company?->name,
            'url' => route('projects.show', $project),
            'todolists' => $project->todolists->map(fn (Todolist $todolist) => [
                'id' => $todolist->id,
                'name' => $todolist->name,
                'description' => $todolist->description,
                'calculation_id' => $todolist->calculation_id,
                'todos' => $todolist->todos->map(fn (Todo $todo) => [
                    'id' => $todo->id,
                    'parent_id' => $todo->parent_id,
                    'calculation_item_id' => $todo->calculation_item_id,
                    'name' => $todo->name,
                    'description' => $todo->description,
                    'days' => $todo->days,
                    'is_done' => $todo->is_done,
                    'assigned_user_id' => $todo->assigned_user_id,
                    'assigned_user_name' => $todo->assignee?->name,
                    'due_date' => $todo->due_date?->toDateString(),
                ])->all(),
            ])->all(),
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID projektu (získáš z nástroje list-projects).')
                ->required(),
        ];
    }
}
