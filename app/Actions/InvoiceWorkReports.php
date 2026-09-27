<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\WorkReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceWorkReports
{
    /**
     * Attach work reports to an invoice. A report may belong to one invoice
     * only, so the whole batch is rejected if any of them is already invoiced.
     *
     * @param  array<int, int>  $workReportIds
     * @param  string|null  $hourlyRate  When set, overrides the rate of every attached report.
     *
     * @throws ValidationException
     */
    public function handle(Invoice $invoice, array $workReportIds, ?string $hourlyRate = null): int
    {
        $workReportIds = array_values(array_unique(array_map('intval', $workReportIds)));

        return DB::transaction(function () use ($invoice, $workReportIds, $hourlyRate) {
            $reports = WorkReport::whereIn('id', $workReportIds)->lockForUpdate()->get(['id', 'invoice_id']);

            if ($reports->count() !== count($workReportIds)) {
                throw ValidationException::withMessages([
                    'work_report_ids' => 'Některé vybrané výkazy neexistují.',
                ]);
            }

            if ($reports->contains(fn (WorkReport $report) => $report->invoice_id !== null && $report->invoice_id !== $invoice->id)) {
                throw ValidationException::withMessages([
                    'work_report_ids' => 'Některé vybrané výkazy už jsou vyfakturované v jiné faktuře.',
                ]);
            }

            return WorkReport::whereIn('id', $workReportIds)->update(array_filter([
                'invoice_id' => $invoice->id,
                'hourly_rate' => $hourlyRate,
            ], fn ($value) => $value !== null));
        });
    }
}
