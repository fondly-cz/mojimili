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

#[Name('delete-work-report')]
#[Title('Smazat výkaz')]
#[Description('Smaže výkaz práce. Vyfakturovaný výkaz smazat nelze – nejdřív ho odeber z faktury (update-invoice).')]
class DeleteWorkReportTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:work_reports,id',
        ], [
            'id.exists' => 'Výkaz s tímto ID neexistuje.',
        ]);

        $report = WorkReport::with('todo:id,name')->findOrFail($validated['id']);

        if ($report->isInvoiced()) {
            return Response::error('Vyfakturovaný výkaz nelze smazat. Nejdřív ho odeber z faktury.');
        }

        $report->delete();

        return Response::text(sprintf(
            'Výkaz %d (%d min k úkolu "%s") byl smazán.',
            $report->id,
            $report->minutes,
            $report->todo?->name,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID mazaného výkazu.')
                ->required(),
        ];
    }
}
