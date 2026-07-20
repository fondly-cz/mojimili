<?php

namespace App\Mcp\Tools;

use App\Actions\SaveCalculation;
use App\Models\Calculation;
use App\Models\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-calculation')]
#[Title('Upravit kalkulaci')]
#[Description('Upraví existující kalkulaci. Vyplň jen pole, která se mají změnit. Uvedeš-li items, nahradí se jimi VŠECHNY položky kalkulace (a smaže se u nich případné dosavadní odsouhlasení zákazníkem); vynecháš-li items, položky zůstanou beze změny. ID kalkulace zjistíš nástrojem list-calculations, ID služeb nástrojem list-services.')]
class UpdateCalculationTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request, SaveCalculation $saveCalculation): Response
    {
        $user = $this->crmUser($request);

        if (! $user) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:calculations,id',
            'customer_name' => 'sometimes|string|max:255',
            'customer_email' => 'sometimes|email|max:255',
            'customer_phone' => 'sometimes|string|max:255',
            'customer_company' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'note' => 'sometimes|nullable|string',
            'show_vat' => 'sometimes|boolean',
            'company_id' => 'sometimes|nullable|integer|exists:companies,id',
            'company_employee_id' => 'sometimes|nullable|integer|exists:company_employees,id',
            'items' => 'sometimes|array|min:1',
            'items.*.service_id' => 'required|integer|exists:services,id',
            'items.*.key' => 'nullable|string|max:64',
            'items.*.parent_key' => 'nullable|string|max:64',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.days' => 'nullable|integer|min:0',
            'items.*.payment_period' => 'nullable|string|in:once,monthly,yearly',
            'items.*.description' => 'nullable|string',
            'items.*.is_required' => 'boolean',
        ], [
            'id.exists' => 'Kalkulace s tímto ID neexistuje. Seznam získáš nástrojem list-calculations.',
            'items.min' => 'Kalkulace musí obsahovat alespoň jednu položku. ID služeb zjistíš nástrojem list-services.',
            'items.*.service_id.exists' => 'Některá služba s tímto ID neexistuje. Ověř ID nástrojem list-services.',
        ]);

        $calculation = Calculation::query()->where('id', $validated['id'])->firstOrFail();

        // Only the fields the client actually sent overwrite the calculation;
        // everything else keeps its current value.
        $attributes = collect($validated)
            ->only(['customer_name', 'customer_email', 'customer_phone', 'customer_company',
                'company_id', 'company_employee_id', 'description', 'note', 'show_vat']);

        if (array_key_exists('items', $validated)) {
            // Full replacement of the item list – rebuild it the same way create does.
            $items = $this->buildItems($validated['items']);

            $duplicateKeys = collect($items)
                ->pluck('unique_id')
                ->duplicates()
                ->unique()
                ->values();

            if ($duplicateKeys->isNotEmpty()) {
                return Response::error(sprintf(
                    'Tyto hodnoty key se v kalkulaci opakují: %s. Každá položka musí mít vlastní unikátní key, jinak by se položka založila dvakrát a podřízené položky by se navázaly jen na jednu z kopií.',
                    $duplicateKeys->implode(', '),
                ));
            }

            $unknownParents = collect($items)
                ->pluck('parent_id')
                ->filter()
                ->diff(collect($items)->pluck('unique_id'));

            if ($unknownParents->isNotEmpty()) {
                return Response::error(sprintf(
                    'Tyto hodnoty parent_key neodpovídají žádnému key v kalkulaci: %s. Nadřazená položka musí být také v seznamu items.',
                    $unknownParents->implode(', '),
                ));
            }

            $saveCalculation->update($calculation, [
                ...$this->currentAttributes($calculation),
                ...$attributes,
                'services' => $items,
            ]);
        } elseif ($attributes->isNotEmpty()) {
            // No items sent – touch only the scalar fields, leave items (and their
            // customer approvals) untouched.
            $calculation->update($attributes->all());
        }

        $calculation->load('items');

        $total = $calculation->items->sum(fn ($item) => (float) $item->price);

        return Response::text(sprintf(
            "Kalkulace pro zákazníka %s byla upravena (ID %d).\n".
            "Položek: %d, součet cen: %s Kč bez DPH, celkem %d dní.\n".
            "Veřejný odkaz pro zákazníka: %s\n".
            'Detail v CRM: %s',
            $calculation->customer_name,
            $calculation->id,
            $calculation->items->count(),
            number_format($total, 2, ',', ' '),
            $calculation->items->sum('days'),
            $calculation->public_url,
            route('calculations.show', $calculation),
        ));
    }

    /**
     * Map the client supplied items onto the shape SaveCalculation expects,
     * falling back to the service catalog for anything left out.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $rawItems): array
    {
        $services = Service::whereIn('id', collect($rawItems)->pluck('service_id'))
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($rawItems as $index => $item) {
            $service = $services->get($item['service_id']);

            if (! $service instanceof Service) {
                continue;
            }

            $items[] = [
                'id' => $service->id,
                'unique_id' => $item['key'] ?? 'item-'.$index,
                'parent_id' => $item['parent_key'] ?? null,
                'price' => $item['price'] ?? round($service->price, 2),
                'days' => $item['days'] ?? $service->days,
                'payment_period' => $item['payment_period'] ?? $service->payment_period->value,
                'description' => $item['description'] ?? $service->description,
                'is_required' => $item['is_required'] ?? false,
            ];
        }

        return $items;
    }

    /**
     * The calculation's current attributes, so a partial update can be merged
     * on top of them before handing the full set to SaveCalculation.
     *
     * @return array<string, mixed>
     */
    private function currentAttributes(Calculation $calculation): array
    {
        return [
            'customer_name' => $calculation->customer_name,
            'customer_email' => $calculation->customer_email,
            'customer_phone' => $calculation->customer_phone,
            'customer_company' => $calculation->customer_company,
            'company_id' => $calculation->company_id,
            'company_employee_id' => $calculation->company_employee_id,
            'description' => $calculation->description,
            'note' => $calculation->note,
            'show_vat' => $calculation->show_vat,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravované kalkulace (získáš z nástroje list-calculations).')
                ->required(),

            'customer_name' => $schema->string()
                ->description('Nové jméno zákazníka (kontaktní osoby).'),

            'customer_email' => $schema->string()
                ->description('Nový e-mail zákazníka.'),

            'customer_phone' => $schema->string()
                ->description('Nový telefon zákazníka.'),

            'customer_company' => $schema->string()
                ->description('Nový název firmy zákazníka, pokud není navázaná přes company_id.'),

            'description' => $schema->string()
                ->description('Nový úvodní text kalkulace zobrazený zákazníkovi.'),

            'note' => $schema->string()
                ->description('Nová interní poznámka ke kalkulaci.'),

            'show_vat' => $schema->boolean()
                ->description('Zobrazit zákazníkovi ceny včetně DPH.'),

            'company_id' => $schema->integer()
                ->description('ID firmy z CRM (nástroj list-companies), na kterou má být kalkulace navázaná.'),

            'company_employee_id' => $schema->integer()
                ->description('ID kontaktní osoby firmy z CRM (nástroj list-companies).'),

            'items' => $schema->array()
                ->description('Nové položky kalkulace v pořadí, v jakém se mají zobrazit. Uvedeš-li je, nahradí VŠECHNY dosavadní položky. Chceš-li položky zachovat, item vynech.')
                ->items($schema->object([
                    'service_id' => $schema->integer()
                        ->description('ID služby z katalogu (nástroj list-services).')
                        ->required(),

                    'key' => $schema->string()
                        ->description('Vlastní klíč položky, na který se lze odkázat z parent_key u podřízené položky, například "hosting".'),

                    'parent_key' => $schema->string()
                        ->description('Klíč nadřazené položky, pod kterou se má tato položka zanořit.'),

                    'price' => $schema->number()
                        ->description('Cena položky v Kč bez DPH. Neuvedeš-li ji, použije se cena z katalogu služby.'),

                    'days' => $schema->integer()
                        ->description('Pracnost položky ve dnech. Neuvedeš-li ji, použije se hodnota z katalogu služby.'),

                    'payment_period' => $schema->string()
                        ->enum(['once', 'monthly', 'yearly'])
                        ->description('Perioda platby položky. Neuvedeš-li ji, použije se hodnota z katalogu služby.'),

                    'description' => $schema->string()
                        ->description('Popis položky pro zákazníka. Neuvedeš-li jej, použije se popis ze služby.'),

                    'is_required' => $schema->boolean()
                        ->description('Povinná položka, kterou zákazník nemůže odškrtnout.')
                        ->default(false),
                ])),
        ];
    }
}
