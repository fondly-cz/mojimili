<?php

namespace App\Mcp\Tools;

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

#[Name('delete-invoice')]
#[Title('Smazat fakturu')]
#[Description('Smaže fakturu z CRM a všechny její výkazy vrátí k fakturaci. Výkazy samotné zůstanou zachované.')]
class DeleteInvoiceTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:invoices,id',
        ], [
            'id.exists' => 'Faktura s tímto ID neexistuje. Seznam získáš nástrojem list-invoices.',
        ]);

        $invoice = Invoice::findOrFail($validated['id']);

        $released = DB::transaction(function () use ($invoice) {
            $released = $invoice->workReports()->update(['invoice_id' => null]);
            $invoice->delete();

            return $released;
        });

        return Response::text(sprintf(
            'Faktura %s byla smazána a %d výkazů se vrátilo k fakturaci.',
            $invoice->number,
            $released,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID mazané faktury.')
                ->required(),
        ];
    }
}
