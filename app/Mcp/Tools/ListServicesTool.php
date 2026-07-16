<?php

namespace App\Mcp\Tools;

use App\Models\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-services')]
#[Title('Seznam služeb')]
#[Description('Vypíše katalog služeb (ceníkové položky) včetně ID, kategorie, nákladu, marže, výsledné ceny, počtu dní a periody platby. Použij pro zjištění ID služeb před vytvořením kalkulace.')]
class ListServicesTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'include_inactive' => 'boolean',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $services = Service::query()
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when(! ($validated['include_inactive'] ?? false), fn ($query) => $query->where('is_active', true))
            ->orderBy('category')
            ->orderBy('name')
            ->limit($validated['limit'] ?? 100)
            ->get();

        if ($services->isEmpty()) {
            return Response::text('Nenalezena žádná služba odpovídající zadání.');
        }

        return Response::text($services->map(fn (Service $service) => [
            'id' => $service->id,
            'name' => $service->name,
            'category' => $service->category,
            'description' => $service->description,
            'cost' => (float) $service->cost,
            'margin' => (float) $service->margin,
            'price' => round($service->price, 2),
            'days' => $service->days,
            'payment_period' => $service->payment_period->value,
            'is_active' => $service->is_active,
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Hledaný výraz v názvu nebo popisu služby.'),

            'category' => $schema->string()
                ->description('Přesný název kategorie, na kterou se má výpis omezit.'),

            'include_inactive' => $schema->boolean()
                ->description('Zahrnout i neaktivní služby.')
                ->default(false),

            'limit' => $schema->integer()
                ->description('Maximální počet vrácených služeb (1-200).')
                ->default(100),
        ];
    }
}
