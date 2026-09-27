<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use App\Models\WorkReport;

/**
 * Shared JSON shape of an invoice for the invoice tools.
 */
trait DescribesInvoices
{
    use DescribesWorkReports;

    /**
     * @return array<string, mixed>
     */
    protected function describeInvoice(Invoice $invoice, bool $withWorkReports = false): array
    {
        $data = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'url' => $invoice->url,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'note' => $invoice->note,
            'work_reports_count' => (int) $invoice->work_reports_count,
            'total_minutes' => (int) $invoice->total_minutes,
            'total_amount' => round((float) $invoice->total_amount, 2),
            'crm_url' => route('invoices.show', $invoice),
        ];

        if ($withWorkReports) {
            $data['work_reports'] = $invoice->workReports
                ->map(fn (WorkReport $report) => $this->describeWorkReport($report))
                ->all();
        }

        return $data;
    }

    /**
     * Reload an invoice with totals and full work report details.
     */
    protected function freshInvoice(int $id): Invoice
    {
        return Invoice::withTotals()
            ->with($this->workReportRelations('workReports.'))
            ->findOrFail($id);
    }
}
