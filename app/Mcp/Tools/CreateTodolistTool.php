<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\Todolist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-todolist')]
#[Title('Založit seznam úkolů')]
#[Description('Založí v projektu nový seznam úkolů a volitelně rovnou i jednotlivé úkoly. Úkoly lze zanořovat: každému dej `key` a podřízenému nastav `parent_key` na klíč rodiče. Chceš-li úkoly převzít z kalkulace, použij místo toho create-todolist-from-calculation.')]
class CreateTodolistTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'todos' => 'nullable|array',
            'todos.*.key' => 'required|string',
            'todos.*.parent_key' => 'nullable|string',
            'todos.*.name' => 'required|string|max:255',
            'todos.*.description' => 'nullable|string',
            'todos.*.days' => 'nullable|integer|min:0',
            'todos.*.due_date' => 'nullable|date',
        ], [
            'project_id.exists' => 'Projekt s tímto ID neexistuje. Seznam získáš nástrojem list-projects, nový založíš nástrojem create-project.',
            'todos.*.key.required' => 'Každému úkolu dej vlastní `key`, aby šlo nastavit zanoření přes `parent_key`.',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        $todolist = DB::transaction(function () use ($project, $validated) {
            $todolist = $project->todolists()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => $project->todolists()->max('sort_order') + 1,
            ]);

            $this->createTodos($todolist, $validated['todos'] ?? []);

            return $todolist->load('todos');
        });

        return Response::text(sprintf(
            "Seznam úkolů \"%s\" byl založen v projektu \"%s\" (todolist_id %d, %d úkolů).\nDetail v CRM: %s",
            $todolist->name,
            $project->name,
            $todolist->id,
            $todolist->todos->count(),
            route('projects.show', $project),
        ));
    }

    /**
     * Create the todos, then wire up parent/child once every key has an id.
     *
     * @param  array<int, array<string, mixed>>  $todos
     */
    private function createTodos(Todolist $todolist, array $todos): void
    {
        $created = [];

        foreach ($todos as $index => $todo) {
            $created[$todo['key']] = $todolist->todos()->create([
                'name' => $todo['name'],
                'description' => $todo['description'] ?? null,
                'days' => $todo['days'] ?? 0,
                'due_date' => $todo['due_date'] ?? null,
                'sort_order' => $index,
            ]);
        }

        foreach ($todos as $todo) {
            $parentKey = $todo['parent_key'] ?? null;

            if ($parentKey && isset($created[$parentKey], $created[$todo['key']])) {
                $created[$todo['key']]->update([
                    'parent_id' => $created[$parentKey]->id,
                ]);
            }
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->description('ID projektu, do kterého seznam patří.')
                ->required(),

            'name' => $schema->string()
                ->description('Název seznamu úkolů, například "Etapa 1".')
                ->required(),

            'description' => $schema->string()
                ->description('Popis seznamu úkolů.'),

            'todos' => $schema->array()
                ->description('Úkoly, které se rovnou založí. Zanoření nastav přes `key` a `parent_key`.')
                ->items($schema->object([
                    'key' => $schema->string()
                        ->description('Tvůj vlastní klíč úkolu, na který se odkazuje `parent_key` podřízených úkolů.')
                        ->required(),

                    'parent_key' => $schema->string()
                        ->description('Klíč nadřazeného úkolu. Vynech u úkolů na nejvyšší úrovni.'),

                    'name' => $schema->string()
                        ->description('Název úkolu.')
                        ->required(),

                    'description' => $schema->string()
                        ->description('Popis úkolu.'),

                    'days' => $schema->integer()
                        ->description('Odhad práce ve dnech.'),

                    'due_date' => $schema->string()
                        ->description('Termín úkolu ve formátu YYYY-MM-DD.'),
                ])),
        ];
    }
}
