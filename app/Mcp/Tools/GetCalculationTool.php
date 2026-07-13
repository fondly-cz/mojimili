<?php

namespace App\Mcp\Tools;

use App\Models\Calculation;
use App\Models\CalculationItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('get-calculation')]
#[Title('Detail kalkulace')]
#[Description('Vrátí detail jedné kalkulace včetně všech položek, jejich zanoření, cen a stavu odsouhlasení zákazníkem.')]
class GetCalculationTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:calculations,id',
        ], [
            'id.exists' => 'Kalkulace s tímto ID neexistuje. Seznam získáš nástrojem list-calculations.',
        ]);

        $calculation = Calculation::with('items')->where('id', $validated['id'])->firstOrFail();

        return Response::text(collect([
            'id' => $calculation->id,
            'customer_name' => $calculation->customer_name,
            'customer_email' => $calculation->customer_email,
            'customer_phone' => $calculation->customer_phone,
            'customer_company' => $calculation->customer_company,
            'description' => $calculation->description,
            'note' => $calculation->note,
            'status' => $calculation->status,
            'show_vat' => $calculation->show_vat,
            'total_price' => (float) $calculation->total_price,
            'total_days' => $calculation->total_days,
            'public_url' => $calculation->public_url,
            'items' => $calculation->items->map(fn (CalculationItem $item) => [
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'service_id' => $item->service_id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => (float) $item->price,
                'days' => $item->days,
                'payment_period' => $item->payment_period->value,
                'is_required' => $item->is_required,
                'is_accepted' => $item->is_accepted,
            ])->all(),
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID kalkulace (získáš z nástroje list-calculations).')
                ->required(),
        ];
    }
}
