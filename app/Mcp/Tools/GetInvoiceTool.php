<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('get-invoice')]
#[Title('Detail faktury')]
#[Description('Vrátí detail faktury včetně všech vyfakturovaných výkazů práce (úkol, projekt, firma, osoba, minuty, sazba, částka).')]
class GetInvoiceTool extends Tool
{
    use DescribesInvoices;
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

        return Response::text(json_encode(
            $this->describeInvoice($this->freshInvoice($validated['id']), withWorkReports: true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID faktury (získáš z nástroje list-invoices).')
                ->required(),
        ];
    }
}
