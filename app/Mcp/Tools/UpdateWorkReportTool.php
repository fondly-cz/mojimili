<?php

namespace App\Mcp\Tools;

use App\Models\WorkReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-work-report')]
#[Title('Upravit výkaz')]
#[Description('Upraví výkaz práce. Vyplň jen pole, která se mají změnit. Vyfakturovaný výkaz upravit nelze – nejdřív ho odeber z faktury (update-invoice). ID výkazů zjistíš nástrojem get-project nebo list-uninvoiced-work-reports.')]
class UpdateWorkReportTool extends Tool
{
    use DescribesWorkReports;
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:work_reports,id',
            'date' => 'sometimes|date',
            'minutes' => 'sometimes|integer|min:1|max:1440',
            'hourly_rate' => 'sometimes|nullable|numeric|min:0|max:99999999',
            'description' => 'sometimes|nullable|string|max:2000',
            'user_id' => 'sometimes|integer|exists:users,id',
        ], [
            'id.exists' => 'Výkaz s tímto ID neexistuje.',
            'user_id.exists' => 'Uživatel s tímto ID v CRM neexistuje. Seznam získáš nástrojem list-users.',
        ]);

        $report = WorkReport::with('todo.todolist.project')->findOrFail($validated['id']);
        unset($validated['id']);

        if ($report->isInvoiced()) {
            return Response::error('Vyfakturovaný výkaz nelze upravit. Nejdřív ho odeber z faktury.');
        }

        if ($validated === []) {
            return Response::error('Neuvedl jsi žádnou změnu. Vyplň alespoň jedno pole, které se má upravit.');
        }

        $changed = array_keys($validated);

        // null means "back to the default": the person's rate in the project, then the project's.
        if (array_key_exists('hourly_rate', $validated) && $validated['hourly_rate'] === null) {
            $validated['hourly_rate'] = $report->todo->todolist->project->rateFor(
                $validated['user_id'] ?? $report->user_id
            );
        }

        $report->update($validated);
        $report->load($this->workReportRelations());

        return Response::text(sprintf(
            "Výkaz %d byl upraven. Změněná pole: %s\n%s",
            $report->id,
            implode(', ', $changed),
            json_encode($this->describeWorkReport($report), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravovaného výkazu.')
                ->required(),

            'date' => $schema->string()
                ->description('Nový den práce ve formátu YYYY-MM-DD.'),

            'minutes' => $schema->integer()
                ->description('Nový odpracovaný čas v minutách.'),

            'hourly_rate' => $schema->number()
                ->description('Nová hodinová sazba v Kč bez DPH. null vrátí sazbu osoby v projektu, případně výchozí sazbu projektu.'),

            'description' => $schema->string()
                ->description('Nový popis odvedené práce.'),

            'user_id' => $schema->integer()
                ->description('ID uživatele, který práci odvedl (list-users).'),
        ];
    }
}
