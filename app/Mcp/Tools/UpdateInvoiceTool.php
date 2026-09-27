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

#[Name('update-invoice')]
#[Title('Upravit fakturu')]
#[Description('Upraví fakturu – číslo, odkaz, datum vystavení, poznámku – a přidá do ní nebo z ní odebere výkazy práce. Odebrané výkazy se vrátí k fakturaci. Vyplň jen to, co se má změnit.')]
class UpdateInvoiceTool extends Tool
{
    use DescribesInvoices;
    use InteractsWithCrmUser;

    public function handle(Request $request, InvoiceWorkReports $invoiceWorkReports): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:invoices,id',
            'number' => 'sometimes|string|max:255',
            'url' => 'sometimes|nullable|url|max:2048',
            'issued_at' => 'sometimes|nullable|date',
            'note' => 'sometimes|nullable|string',
            'add_work_report_ids' => 'sometimes|array|min:1',
            'add_work_report_ids.*' => 'integer',
            'remove_work_report_ids' => 'sometimes|array|min:1',
            'remove_work_report_ids.*' => 'integer',
            'hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ], [
            'id.exists' => 'Faktura s tímto ID neexistuje. Seznam získáš nástrojem list-invoices.',
        ]);

        $invoice = Invoice::findOrFail($validated['id']);
        $fields = collect($validated)->only(['number', 'url', 'issued_at', 'note'])->all();
        $add = $validated['add_work_report_ids'] ?? [];
        $remove = $validated['remove_work_report_ids'] ?? [];

        if ($fields === [] && $add === [] && $remove === []) {
            return Response::error('Neuvedl jsi žádnou změnu. Vyplň alespoň jedno pole, které se má upravit.');
        }

        if (isset($validated['hourly_rate']) && $add === []) {
            return Response::error('Hromadná sazba (hourly_rate) se použije jen na přidávané výkazy – uveď add_work_report_ids.');
        }

        DB::transaction(function () use ($invoice, $fields, $add, $remove, $validated, $invoiceWorkReports) {
            $invoice->update($fields);

            if ($remove !== []) {
                $invoice->workReports()->whereIn('id', $remove)->update(['invoice_id' => null]);
            }

            if ($add !== []) {
                $invoiceWorkReports->handle(
                    $invoice,
                    $add,
                    isset($validated['hourly_rate']) ? (string) $validated['hourly_rate'] : null,
                );
            }
        });

        return Response::text(sprintf(
            "Faktura %s byla upravena (invoice_id %d).\n%s",
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
            'id' => $schema->integer()
                ->description('ID upravované faktury.')
                ->required(),

            'number' => $schema->string()
                ->description('Nové číslo faktury.'),

            'url' => $schema->string()
                ->description('Nový odkaz na fakturu. null odkaz zruší.'),

            'issued_at' => $schema->string()
                ->description('Nové datum vystavení ve formátu YYYY-MM-DD.'),

            'note' => $schema->string()
                ->description('Nová interní poznámka.'),

            'add_work_report_ids' => $schema->array()
                ->description('ID nevyfakturovaných výkazů, které se do faktury přidají.')
                ->items($schema->integer()),

            'remove_work_report_ids' => $schema->array()
                ->description('ID výkazů, které se z faktury odeberou a vrátí k fakturaci.')
                ->items($schema->integer()),

            'hourly_rate' => $schema->number()
                ->description('Hromadná hodinová sazba v Kč bez DPH pro přidávané výkazy. Vynecháš-li ji, výkazy si ponechají svou sazbu.'),
        ];
    }
}
