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

#[Name('create-service')]
#[Title('Přidat službu')]
#[Description('Vytvoří novou službu (ceníkovou položku) v katalogu CRM. Vyžaduje roli administrátora. Cena se počítá jako cost * (1 + margin / 100).')]
class CreateServiceTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        $user = $this->crmUser($request);

        if (! $user) {
            return $this->accessDenied();
        }

        if (! $user->isAdmin()) {
            return $this->adminRequired();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cost' => 'required|numeric|min:0',
            'margin' => 'required|numeric|min:0',
            'days' => 'required|integer|min:0',
            'icon' => 'nullable|string|max:10',
            'payment_period' => 'required|string|in:once,monthly,yearly',
            'is_active' => 'boolean',
        ], [
            'category.required' => 'Uveď kategorii služby, například "Vývoj" nebo "Hosting".',
            'payment_period.in' => 'Perioda platby musí být "once", "monthly" nebo "yearly".',
        ]);

        $service = Service::create($validated);

        return Response::text(sprintf(
            'Služba "%s" byla vytvořena (ID %d, kategorie %s, náklad %s Kč, marže %s %%, výsledná cena %s Kč, %d dní, perioda %s).',
            $service->name,
            $service->id,
            $service->category,
            $service->cost,
            $service->margin,
            round($service->price, 2),
            $service->days,
            $service->payment_period->value,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Název služby.')
                ->required(),

            'category' => $schema->string()
                ->description('Kategorie služby, například "Vývoj", "Design", "Hosting".')
                ->required(),

            'description' => $schema->string()
                ->description('Popis služby, který se předvyplní do položky kalkulace.'),

            'cost' => $schema->number()
                ->description('Náklad na službu v Kč bez DPH.')
                ->required(),

            'margin' => $schema->number()
                ->description('Marže v procentech, například 30 znamená +30 %.')
                ->required(),

            'days' => $schema->integer()
                ->description('Odhadovaná pracnost ve dnech.')
                ->required(),

            'payment_period' => $schema->string()
                ->enum(['once', 'monthly', 'yearly'])
                ->description('Perioda platby: jednorázově, měsíčně nebo ročně.')
                ->required(),

            'icon' => $schema->string()
                ->description('Volitelná emoji ikona služby, například 🚀.'),

            'is_active' => $schema->boolean()
                ->description('Zda je služba aktivní a nabízí se v kalkulacích.')
                ->default(true),
        ];
    }
}
