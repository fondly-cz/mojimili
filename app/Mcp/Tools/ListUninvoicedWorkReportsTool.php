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

#[Name('list-uninvoiced-work-reports')]
#[Title('Výkazy k fakturaci')]
#[Description('Vypíše dosud nevyfakturované výkazy práce (stránka K fakturaci), volitelně filtrované podle projektu, firmy, osoby a období. Vrátí i součet minut a částky. ID výkazů pak předáš do create-invoice nebo update-invoice.')]
class ListUninvoicedWorkReportsTool extends Tool
{
    use DescribesWorkReports;
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $reports = WorkReport::uninvoiced()
            ->with($this->workReportRelations())
            ->when($validated['project_id'] ?? null, fn ($q, $projectId) => $q->whereHas(
                'todo.todolist', fn ($l) => $l->where('project_id', $projectId)
            ))
            ->when($validated['company_id'] ?? null, fn ($q, $companyId) => $q->whereHas(
                'todo.todolist.project', fn ($p) => $p->where('company_id', $companyId)
            ))
            ->when($validated['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($validated['date_from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($validated['date_to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($reports->isEmpty()) {
            return Response::text('Žádné nevyfakturované výkazy odpovídající zadání.');
        }

        return Response::text(collect([
            'count' => $reports->count(),
            'total_minutes' => $reports->sum('minutes'),
            'total_amount' => round($reports->sum('amount'), 2),
            'work_reports' => $reports->map(fn (WorkReport $report) => $this->describeWorkReport($report))->all(),
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->description('Jen výkazy tohoto projektu (list-projects).'),

            'company_id' => $schema->integer()
                ->description('Jen výkazy projektů této firmy (list-companies).'),

            'user_id' => $schema->integer()
                ->description('Jen výkazy této osoby (list-users).'),

            'date_from' => $schema->string()
                ->description('Jen výkazy od tohoto dne včetně, YYYY-MM-DD.'),

            'date_to' => $schema->string()
                ->description('Jen výkazy do tohoto dne včetně, YYYY-MM-DD.'),
        ];
    }
}
