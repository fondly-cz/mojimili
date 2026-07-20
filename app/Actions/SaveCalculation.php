<?php

namespace App\Actions;

use App\Models\Calculation;
use App\Models\Service;
use InvalidArgumentException;

class SaveCalculation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId): Calculation
    {
        $calculation = Calculation::create([
            ...$this->attributes($data),
            'user_id' => $userId,
            'total_price' => 0,
            'total_days' => 0,
        ]);

        $this->syncItems($calculation, $data['services']);

        return $calculation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Calculation $calculation, array $data): Calculation
    {
        $calculation->update($this->attributes($data));

        $calculation->items()->delete();

        $this->syncItems($calculation, $data['services']);

        return $calculation;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'customer_company' => $data['customer_company'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'company_employee_id' => $data['company_employee_id'] ?? null,
            'description' => $data['description'] ?? null,
            'note' => $data['note'] ?? null,
            'show_vat' => (bool) ($data['show_vat'] ?? false),
        ];
    }

    /**
     * Persist the calculation items, then wire up the parent/child hierarchy
     * using the client supplied `unique_id` / `parent_id` keys.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Calculation $calculation, array $items): void
    {
        $duplicateKeys = collect($items)->pluck('unique_id')->duplicates();

        if ($duplicateKeys->isNotEmpty()) {
            // Two items sharing a unique_id would each be inserted, but only the last
            // would be registered as a possible parent – leaving an orphan duplicate
            // with no children. Fail loudly instead of silently corrupting the tree.
            throw new InvalidArgumentException(sprintf(
                'Položky kalkulace musí mít unikátní unique_id. Duplicitní hodnoty: %s.',
                $duplicateKeys->unique()->implode(', '),
            ));
        }

        $services = Service::whereIn('id', collect($items)->pluck('id'))->get();
        $created = [];

        foreach ($items as $index => $itemData) {
            $service = $services->firstWhere('id', $itemData['id']);

            if (! $service) {
                continue;
            }

            $created[$itemData['unique_id']] = $calculation->items()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'description' => $itemData['description'] ?? $service->description,
                'cost' => 0, // Costs are individual per calculation.
                'margin' => 0,
                'price' => $itemData['price'],
                'days' => $itemData['days'],
                'payment_period' => $itemData['payment_period'],
                'is_accepted' => false,
                'is_required' => $itemData['is_required'] ?? false,
                'sort_order' => $index,
            ]);
        }

        foreach ($items as $itemData) {
            $parentKey = $itemData['parent_id'] ?? null;

            if ($parentKey && isset($created[$parentKey], $created[$itemData['unique_id']])) {
                $created[$itemData['unique_id']]->update([
                    'parent_id' => $created[$parentKey]->id,
                ]);
            }
        }
    }
}
