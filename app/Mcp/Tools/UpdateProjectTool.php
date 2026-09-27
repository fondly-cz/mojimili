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
            'hourly_rate' => 'sometimes|nullable|numeric|min:0',
            'user_rates' => 'sometimes|array',
            'user_rates.*.user_id' => 'required|integer|distinct|exists:users,id',
            'user_rates.*.hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ], [
            'id.exists' => 'Projekt s tímto ID neexistuje. Seznam získáš nástrojem list-projects.',
            'status.in' => 'Stav projektu musí být "active", "on_hold", "done" nebo "archived".',
        ]);

        $project = Project::findOrFail($validated['id']);
        unset($validated['id']);

        if ($validated === []) {
            return Response::error('Neuvedl jsi žádnou změnu. Vyplň alespoň jedno pole, které se má upravit.');
        }

        DB::transaction(function () use ($project, $validated) {
            $project->update(collect($validated)->except('user_rates')->all());

            if (array_key_exists('user_rates', $validated)) {
                // A null rate removes the person's override.
                $project->userRates()->sync(collect($validated['user_rates'])
                    ->filter(fn ($rate) => ($rate['hourly_rate'] ?? null) !== null)
                    ->mapWithKeys(fn ($rate) => [$rate['user_id'] => ['hourly_rate' => $rate['hourly_rate']]])
                    ->all());
            }
        });

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

            'hourly_rate' => $schema->number()
                ->description('Nová výchozí hodinová sazba projektu v Kč bez DPH (už vykázané výkazy se nemění). null sazbu zruší.'),

            'user_rates' => $schema->array()
                ->description('Vlastní hodinové sazby osob v projektu – nahradí všechny dosavadní. Osoba bez sazby (nebo s hourly_rate null) vykazuje za výchozí sazbu projektu. ID osob zjistíš nástrojem list-users.')
                ->items($schema->object([
                    'user_id' => $schema->integer()->description('ID uživatele.')->required(),
                    'hourly_rate' => $schema->number()->description('Sazba osoby v Kč bez DPH.'),
                ])),
        ];
    }
}
