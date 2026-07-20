<?php

namespace App\Mcp\Tools;

use App\Actions\CreateTodolistFromCalculation;
use App\Models\Calculation;
use App\Models\Project;
use App\Models\Todo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-todolist-from-calculation')]
#[Title('Seznam úkolů z kalkulace')]
#[Description('Převede položky kalkulace na úkoly v projektu. Neuvedeš-li item_ids, převezmou se všechny položky, které zákazník odsouhlasil. Zanoření položek se do úkolů přenese; vybereš-li podpoložku, nadřazené položky se doplní automaticky.')]
class CreateTodolistFromCalculationTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request, CreateTodolistFromCalculation $createTodolist): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'calculation_id' => 'required|integer|exists:calculations,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'project_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ], [
            'calculation_id.exists' => 'Kalkulace s tímto ID neexistuje. Seznam získáš nástrojem list-calculations.',
            'project_id.exists' => 'Projekt s tímto ID neexistuje. Seznam získáš nástrojem list-projects.',
        ]);

        $calculation = Calculation::findOrFail($validated['calculation_id']);
        $itemIds = $validated['item_ids'] ?? null;

        if (empty($validated['project_id']) && empty($validated['project_name'])) {
            return Response::error(
                'Uveď project_id existujícího projektu (list-projects), nebo project_name pro založení nového.'
            );
        }

        if ($itemIds !== null) {
            // Items from another calculation would silently vanish from the result.
            $ownedIds = $calculation->items()->whereIn('id', $itemIds)->pluck('id')->all();
            $foreign = array_diff($itemIds, $ownedIds);

            if ($foreign !== []) {
                return Response::error(sprintf(
                    'Položky s ID %s do této kalkulace nepatří. Platná ID zjistíš nástrojem get-calculation.',
                    implode(', ', $foreign),
                ));
            }
        } elseif (! $calculation->items()->where('is_accepted', true)->exists()) {
            return Response::error(
                'Kalkulace zatím nemá žádné odsouhlasené položky, takže by seznam úkolů zůstal prázdný. '.
                'Vyber položky ručně přes item_ids (ID zjistíš nástrojem get-calculation).'
            );
        }

        $project = ! empty($validated['project_id'])
            ? Project::findOrFail($validated['project_id'])
            : Project::create([
                'name' => $validated['project_name'],
                'company_id' => $calculation->company_id,
                'company_employee_id' => $calculation->company_employee_id,
                'user_id' => $user->id,
                'status' => 'active',
            ]);

        $todolist = $createTodolist->handle($calculation, $project, $itemIds, $validated['name'] ?? null);

        $todoLines = $todolist->todos
            ->map(fn (Todo $todo) => sprintf(
                '  - %s (todo_id %d)%s',
                $todo->name,
                $todo->id,
                $todo->parent_id ? ' – podúkol' : '',
            ))
            ->implode("\n");

        return Response::text(sprintf(
            "Seznam úkolů \"%s\" byl vytvořen z kalkulace v projektu \"%s\" (todolist_id %d, project_id %d).\nÚkoly:\n%s\nDetail v CRM: %s",
            $todolist->name,
            $project->name,
            $todolist->id,
            $project->id,
            $todoLines,
            route('projects.show', $project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'calculation_id' => $schema->integer()
                ->description('ID kalkulace, ze které se úkoly převezmou.')
                ->required(),

            'project_id' => $schema->integer()
                ->description('ID existujícího projektu. Nevyplňuj, chceš-li založit nový projekt přes project_name.'),

            'project_name' => $schema->string()
                ->description('Název nového projektu, který se založí. Firma se převezme z kalkulace. Ignoruje se, je-li vyplněné project_id.'),

            'name' => $schema->string()
                ->description('Název seznamu úkolů. Výchozí je název firmy nebo jméno zákazníka z kalkulace.'),

            'item_ids' => $schema->array()
                ->description('ID položek kalkulace, které se mají převést na úkoly. Vynecháš-li je, použijí se všechny odsouhlasené položky.')
                ->items($schema->integer()),
        ];
    }
}
