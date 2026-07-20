<?php

namespace App\Mcp\Tools;

use App\Actions\SaveCalculation;
use App\Models\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-calculation')]
#[Title('Vytvořit kalkulaci')]
#[Description('Vytvoří novou kalkulaci (nabídku) pro zákazníka ze služeb v katalogu a vrátí veřejnou URL, kterou lze poslat zákazníkovi ke schválení. ID služeb si nejdřív zjisti nástrojem list-services.')]
class CreateCalculationTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request, SaveCalculation $saveCalculation): Response
    {
        $user = $this->crmUser($request);

        if (! $user) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'show_vat' => 'boolean',
            'company_id' => 'nullable|integer|exists:companies,id',
            'company_employee_id' => 'nullable|integer|exists:company_employees,id',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|integer|exists:services,id',
            'items.*.key' => 'nullable|string|max:64',
            'items.*.parent_key' => 'nullable|string|max:64',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.days' => 'nullable|integer|min:0',
            'items.*.payment_period' => 'nullable|string|in:once,monthly,yearly',
            'items.*.description' => 'nullable|string',
            'items.*.is_required' => 'boolean',
        ], [
            'items.required' => 'Kalkulace musí obsahovat alespoň jednu položku. ID služeb zjistíš nástrojem list-services.',
            'items.*.service_id.exists' => 'Některá služba s tímto ID neexistuje. Ověř ID nástrojem list-services.',
        ]);

        $services = Service::whereIn('id', collect($validated['items'])->pluck('service_id'))
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($validated['items'] as $index => $item) {
            $service = $services->get($item['service_id']);

            if (! $service instanceof Service) {
                continue;
            }

            // Anything the client leaves out falls back to the service catalog.
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

        $calculation = $saveCalculation->create([...$validated, 'services' => $items], $user->id);

        $calculation->load('items');

        $total = $calculation->items->sum(fn ($item) => (float) $item->price);

        return Response::text(sprintf(
            "Kalkulace pro zákazníka %s byla vytvořena (ID %d).\n".
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
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_name' => $schema->string()
                ->description('Jméno zákazníka (kontaktní osoby).')
                ->required(),

            'customer_email' => $schema->string()
                ->description('E-mail zákazníka.')
                ->required(),

            'customer_phone' => $schema->string()
                ->description('Telefon zákazníka.')
                ->required(),

            'customer_company' => $schema->string()
                ->description('Název firmy zákazníka, pokud není navázaná přes company_id.'),

            'description' => $schema->string()
                ->description('Úvodní text kalkulace zobrazený zákazníkovi.'),

            'note' => $schema->string()
                ->description('Interní poznámka ke kalkulaci.'),

            'show_vat' => $schema->boolean()
                ->description('Zobrazit zákazníkovi ceny včetně DPH.')
                ->default(false),

            'company_id' => $schema->integer()
                ->description('ID firmy z CRM (nástroj list-companies), pokud má být kalkulace navázaná na existující firmu.'),

            'company_employee_id' => $schema->integer()
                ->description('ID kontaktní osoby firmy z CRM (nástroj list-companies).'),

            'items' => $schema->array()
                ->description('Položky kalkulace v pořadí, v jakém se mají zobrazit.')
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
                ]))
                ->required(),
        ];
    }
}
