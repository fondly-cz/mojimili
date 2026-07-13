<?php

namespace App\Mcp\Tools;

use App\Models\Calculation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-calculations')]
#[Title('Seznam kalkulací')]
#[Description('Vypíše kalkulace (nabídky) v CRM, volitelně filtrované podle zákazníka nebo stavu. Detail včetně položek získáš nástrojem get-calculation.')]
class ListCalculationsTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,confirmed',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $calculations = Calculation::query()
            ->withCount('items')
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_company', 'like', "%{$search}%")
            ))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->limit($validated['limit'] ?? 25)
            ->get();

        if ($calculations->isEmpty()) {
            return Response::text('Nenalezena žádná kalkulace odpovídající zadání.');
        }

        return Response::text($calculations->map(fn (Calculation $calculation) => [
            'id' => $calculation->id,
            'customer_name' => $calculation->customer_name,
            'customer_company' => $calculation->customer_company,
            'customer_email' => $calculation->customer_email,
            'status' => $calculation->status,
            'items_count' => $calculation->items_count,
            'total_price' => (float) $calculation->total_price,
            'total_days' => $calculation->total_days,
            'created_at' => $calculation->created_at->toDateString(),
            'public_url' => $calculation->public_url,
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Hledaný výraz ve jménu zákazníka, e-mailu nebo názvu firmy.'),

            'status' => $schema->string()
                ->enum(['draft', 'confirmed'])
                ->description('Stav kalkulace: draft = rozpracovaná, confirmed = zákazníkem potvrzená.'),

            'limit' => $schema->integer()
                ->description('Maximální počet vrácených kalkulací (1-100).')
                ->default(25),
        ];
    }
}
