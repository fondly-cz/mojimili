<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-invoices')]
#[Title('Seznam faktur')]
#[Description('Vypíše faktury evidované v CRM (číslo, odkaz, datum vystavení, počet výkazů, minuty a částku bez DPH), nejnovější první. Detail s výkazy získáš nástrojem get-invoice.')]
class ListInvoicesTool extends Tool
{
    use DescribesInvoices;
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $invoices = Invoice::query()
            ->withTotals()
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('number', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
            ))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit($validated['limit'] ?? 25)
            ->get();

        if ($invoices->isEmpty()) {
            return Response::text('Nenalezena žádná faktura odpovídající zadání.');
        }

        return Response::text($invoices
            ->map(fn (Invoice $invoice) => $this->describeInvoice($invoice))
            ->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Hledaný výraz v čísle nebo poznámce faktury.'),

            'limit' => $schema->integer()
                ->description('Maximální počet vrácených faktur (1-100).')
                ->default(25),
        ];
    }
}
