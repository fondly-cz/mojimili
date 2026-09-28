<?php

namespace App\Mcp\Tools;

use App\Enums\TodoPriority;
use App\Models\Label;
use App\Models\Todo;
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

#[Name('create-todo')]
#[Title('Přidat úkol')]
#[Description('Přidá jeden úkol do existujícího seznamu úkolů, nebo jako podúkol pod existující úkol (stačí parent_id, seznam se převezme od rodiče). ID seznamů a úkolů zjistíš nástrojem get-project. Celý nový seznam i s úkoly založíš nástrojem create-todolist.')]
class CreateTodoTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'todolist_id' => 'required_without:parent_id|nullable|integer|exists:todolists,id',
            'parent_id' => 'nullable|integer|exists:todos,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'nullable|integer|min:0',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
            ...Todo::detailRules(),
            'labels' => 'nullable|array|max:50',
            'labels.*' => 'string|max:100',
        ], [
            'todolist_id.required_without' => 'Uveď todolist_id seznamu, nebo parent_id úkolu, pod který se má podúkol přidat.',
            'todolist_id.exists' => 'Seznam úkolů s tímto ID neexistuje. ID zjistíš nástrojem get-project.',
            'parent_id.exists' => 'Nadřazený úkol s tímto ID neexistuje. ID zjistíš nástrojem get-project.',
            'assigned_user_id.exists' => 'Uživatel s tímto ID v CRM neexistuje.',
        ]);

        $parent = isset($validated['parent_id']) ? Todo::findOrFail($validated['parent_id']) : null;
        $todolistId = $parent?->todolist_id ?? $validated['todolist_id'];

        if ($parent && isset($validated['todolist_id']) && $validated['todolist_id'] !== $parent->todolist_id) {
            return Response::error('Nadřazený úkol patří do jiného seznamu. Vynech todolist_id, převezme se od rodiče.');
        }

        $todolist = Todolist::with('project')->findOrFail($todolistId);

        $todo = DB::transaction(function () use ($validated, $todolist, $parent, $user) {
            $todo = $todolist->todos()->create([
                ...collect($validated)->except(['todolist_id', 'labels'])->all(),
                'parent_id' => $parent?->id,
                'days' => $validated['days'] ?? 0,
                'created_by_user_id' => $user->id,
                'sort_order' => Todo::where('todolist_id', $todolist->id)->where('parent_id', $parent?->id)->max('sort_order') + 1,
            ]);

            if (! empty($validated['labels'])) {
                $todo->labels()->sync(Label::idsForNames($validated['labels']));
            }

            return $todo;
        });

        return Response::text(sprintf(
            "Úkol \"%s\" byl přidán do seznamu \"%s\" (todo_id %d%s).\nDetail v CRM: %s",
            $todo->name,
            $todolist->name,
            $todo->id,
            $parent ? sprintf(', podúkol úkolu "%s"', $parent->name) : '',
            route('todos.show', $todo),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'todolist_id' => $schema->integer()
                ->description('ID seznamu úkolů. U podúkolu ho můžeš vynechat.'),

            'parent_id' => $schema->integer()
                ->description('ID úkolu, pod který se má nový úkol vložit jako podúkol.'),

            'name' => $schema->string()
                ->description('Název úkolu.')
                ->required(),

            'description' => $schema->string()
                ->description('Popis úkolu (HTML, Markdown nebo prostý text).'),

            'days' => $schema->integer()
                ->description('Odhad práce ve dnech.'),

            'estimated_minutes' => $schema->integer()
                ->description('Odhadovaný čas práce v minutách.'),

            'priority' => $schema->string()
                ->enum(array_column(TodoPriority::cases(), 'value'))
                ->description('Priorita úkolu: high, medium, low.'),

            'assigned_user_id' => $schema->integer()
                ->description('ID řešitele (zjistíš nástrojem list-users).'),

            'due_date' => $schema->string()
                ->description('Termín ve formátu YYYY-MM-DD.'),

            'due_time' => $schema->string()
                ->description('Čas termínu ve formátu HH:MM.'),

            'labels' => $schema->array()
                ->description('Názvy štítků; chybějící štítky se založí.')
                ->items($schema->string()),
        ];
    }
}
