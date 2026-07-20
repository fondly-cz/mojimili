<?php

namespace App\Mcp\Tools;

use App\Models\Todo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-todo')]
#[Title('Upravit úkol')]
#[Description('Upraví jeden úkol – označí ho za hotový, přiřadí řešitele, nastaví termín nebo změní název. Vyplň jen pole, která se mají změnit. ID úkolů zjistíš nástrojem get-project.')]
class UpdateTodoTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:todos,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'days' => 'sometimes|integer|min:0',
            'is_done' => 'sometimes|boolean',
            'assigned_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'due_date' => 'sometimes|nullable|date',
        ], [
            'id.exists' => 'Úkol s tímto ID neexistuje. ID úkolů zjistíš nástrojem get-project.',
            'assigned_user_id.exists' => 'Uživatel s tímto ID v CRM neexistuje.',
        ]);

        $todo = Todo::findOrFail($validated['id']);
        unset($validated['id']);

        if ($validated === []) {
            return Response::error('Neuvedl jsi žádnou změnu. Vyplň alespoň jedno pole, které se má upravit.');
        }

        $changed = array_keys($validated);

        if (array_key_exists('is_done', $validated)) {
            $validated['completed_at'] = $validated['is_done'] ? now() : null;
        }

        $todo->update($validated);
        $todo->load('todolist.project');

        return Response::text(sprintf(
            "Úkol \"%s\" byl upraven (todo_id %d, %s).\nZměněná pole: %s\nDetail v CRM: %s",
            $todo->name,
            $todo->id,
            $todo->is_done ? 'hotový' : 'nedokončený',
            implode(', ', $changed),
            route('projects.show', $todo->todolist->project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravovaného úkolu.')
                ->required(),

            'name' => $schema->string()
                ->description('Nový název úkolu.'),

            'description' => $schema->string()
                ->description('Nový popis úkolu.'),

            'days' => $schema->integer()
                ->description('Nový odhad práce ve dnech.'),

            'is_done' => $schema->boolean()
                ->description('true = úkol je hotový (zapíše se čas dokončení), false = vrátí se mezi nedokončené.'),

            'assigned_user_id' => $schema->integer()
                ->description('ID uživatele CRM, kterému se úkol přiřadí. null přiřazení zruší.'),

            'due_date' => $schema->string()
                ->description('Termín úkolu ve formátu YYYY-MM-DD. null termín zruší.'),
        ];
    }
}
