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

#[Name('update-service')]
#[Title('Upravit službu')]
#[Description('Upraví existující službu v katalogu. Vyplň jen ta pole, která se mají změnit. Vyžaduje roli administrátora.')]
class UpdateServiceTool extends Tool
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
            'id' => 'required|integer|exists:services,id',
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'cost' => 'sometimes|numeric|min:0',
            'margin' => 'sometimes|numeric|min:0',
            'days' => 'sometimes|integer|min:0',
            'icon' => 'sometimes|nullable|string|max:10',
            'payment_period' => 'sometimes|string|in:once,monthly,yearly',
            'is_active' => 'sometimes|boolean',
        ], [
            'id.exists' => 'Služba s tímto ID neexistuje. Nejdřív si ID ověř pomocí nástroje list-services.',
        ]);

        $service = Service::findOrFail($validated['id']);
        $service->update(collect($validated)->except('id')->all());

        return Response::text(sprintf(
            'Služba "%s" (ID %d) byla upravena. Aktuálně: náklad %s Kč, marže %s %%, cena %s Kč, %d dní, perioda %s, %s.',
            $service->name,
            $service->id,
            $service->cost,
            $service->margin,
            round($service->price, 2),
            $service->days,
            $service->payment_period->value,
            $service->is_active ? 'aktivní' : 'neaktivní',
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravované služby (získáš z nástroje list-services).')
                ->required(),

            'name' => $schema->string()->description('Nový název služby.'),
            'category' => $schema->string()->description('Nová kategorie služby.'),
            'description' => $schema->string()->description('Nový popis služby.'),
            'cost' => $schema->number()->description('Nový náklad v Kč bez DPH.'),
            'margin' => $schema->number()->description('Nová marže v procentech.'),
            'days' => $schema->integer()->description('Nová pracnost ve dnech.'),
            'icon' => $schema->string()->description('Nová emoji ikona.'),

            'payment_period' => $schema->string()
                ->enum(['once', 'monthly', 'yearly'])
                ->description('Nová perioda platby.'),

            'is_active' => $schema->boolean()
                ->description('Zda se služba nabízí v kalkulacích.'),
        ];
    }
}
