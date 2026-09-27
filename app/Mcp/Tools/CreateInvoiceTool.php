<?php

namespace App\Mcp\Tools;

use App\Actions\InvoiceWorkReports;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-invoice')]
#[Title('Vyfakturovat výkazy')]
#[Description('Zaeviduje fakturu a označí vybrané výkazy práce jako vyfakturované. Samotnou fakturu CRM nevystavuje – ulož jen její číslo a odkaz (např. na fakturu ve Freelu nebo účetnictví). Jedna faktura smí obsahovat výkazy z více projektů i firem, každý výkaz ale může být jen v jedné faktuře. ID výkazů zjistíš nástrojem list-uninvoiced-work-reports.')]
class CreateInvoiceTool extends Tool
{
    use DescribesInvoices;
    use InteractsWithCrmUser;

    public function handle(Request $request, InvoiceWorkReports $invoiceWorkReports): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'number' => 'required|string|max:255',
            'url' => 'nullable|url|max:2048',
            'issued_at' => 'nullable|date',
            'note' => 'nullable|string',
            'work_report_ids' => 'required|array|min:1',
            'work_report_ids.*' => 'integer',
            'hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ]);

        $invoice = DB::transaction(function () use ($validated, $user, $invoiceWorkReports) {
            $invoice = Invoice::create([
                ...collect($validated)->only(['number', 'url', 'issued_at', 'note'])->all(),
                'user_id' => $user->id,
            ]);

            $invoiceWorkReports->handle(
                $invoice,
                $validated['work_report_ids'],
                isset($validated['hourly_rate']) ? (string) $validated['hourly_rate'] : null,
            );

            return $invoice;
        });

        return Response::text(sprintf(
            "Faktura %s byla zaevidována (invoice_id %d) a výkazy označeny jako vyfakturované.\n%s",
            $invoice->number,
            $invoice->id,
            json_encode($this->describeInvoice($this->freshInvoice($invoice->id), withWorkReports: true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'number' => $schema->string()
                ->description('Číslo faktury.')
                ->required(),

            'url' => $schema->string()
                ->description('Odkaz na fakturu (Freelo, účetní systém…).'),

            'issued_at' => $schema->string()
                ->description('Datum vystavení ve formátu YYYY-MM-DD.'),

            'note' => $schema->string()
                ->description('Interní poznámka k faktuře.'),

            'work_report_ids' => $schema->array()
                ->description('ID výkazů práce, které faktura pokrývá.')
                ->items($schema->integer())
                ->required(),

            'hourly_rate' => $schema->number()
                ->description('Hromadná hodinová sazba v Kč bez DPH, která přepíše sazbu všech fakturovaných výkazů. Vynecháš-li ji, výkazy si ponechají svou sazbu.'),
        ];
    }
}
