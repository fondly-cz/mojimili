<?php

namespace App\Actions;

use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Service;
use App\Support\ActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
            ...$this->createdAt($data, now()),
            'user_id' => $userId,
            'total_price' => 0,
            'total_days' => 0,
        ]);

        $this->syncItems($calculation, $data['services']);

        return $calculation;
    }

    /**
     * With $keepItems the submitted items are matched to the existing ones by their id (`unique_id`),
     * so the customer's approval and the todos linked to an item survive an edit. Without it
     * (e.g. the MCP tools, whose keys are not ids) all items are replaced and approvals reset.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Calculation $calculation, array $data, bool $keepItems = false): Calculation
    {
        $calculation->update([
            ...$this->attributes($data),
            ...$this->createdAt($data, $calculation->created_at),
        ]);

        if ($keepItems) {
            $this->updateItems($calculation, $data['services']);

            return $calculation;
        }

        $calculation->items()->delete();

        $this->syncItems($calculation, $data['services']);

        ActivityLog::noteCalculation($calculation, 'calculation.items_replaced', ['count' => count($data['services'])]);

        return $calculation;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = [
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

        // Callers that don't send a validity (e.g. the MCP tools) keep the current one.
        if (isset($data['valid_days'])) {
            $attributes['valid_days'] = (int) $data['valid_days'];
        }

        return $attributes;
    }

    /**
     * Only the date of `created_at` is editable – the time of day is taken from $timeFrom.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, Carbon>
     */
    private function createdAt(array $data, CarbonInterface $timeFrom): array
    {
        if (empty($data['created_at'])) {
            return [];
        }

        return ['created_at' => Carbon::parse($data['created_at'])->setTimeFrom($timeFrom)];
    }

    /**
     * Persist the calculation items, then wire up the parent/child hierarchy
     * using the client supplied `unique_id` / `parent_id` keys.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Calculation $calculation, array $items): void
    {
        // Two items sharing a unique_id would each be inserted, but only the last
        // would be registered as a possible parent – leaving an orphan duplicate
        // with no children. Fail loudly instead of silently corrupting the tree.
        $this->assertUniqueKeys($items);

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

    /**
     * Update the items in place: existing ones keep their id (and approval), new ones are added,
     * missing ones removed. Every difference is written to the calculation's history.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function updateItems(Calculation $calculation, array $items): void
    {
        $this->assertUniqueKeys($items);

        $services = Service::whereIn('id', collect($items)->pluck('id'))->get();
        $existing = $calculation->items()->get()->keyBy(fn (CalculationItem $item) => (string) $item->id);
        $kept = [];
        $byKey = [];
        $parentChanged = [];

        foreach ($items as $index => $itemData) {
            $service = $services->firstWhere('id', $itemData['id']);

            if (! $service) {
                continue;
            }

            $attributes = [
                'description' => $itemData['description'] ?? $service->description,
                'price' => $itemData['price'],
                'days' => $itemData['days'],
                'payment_period' => $itemData['payment_period'],
                'is_required' => $itemData['is_required'] ?? false,
                'sort_order' => $index,
            ];

            $item = $existing->get((string) $itemData['unique_id']);

            if ($item) {
                $item->fill($attributes);
                $changes = $this->describeItemChanges($item);
                $item->save();

                if ($changes !== []) {
                    ActivityLog::noteCalculation($calculation, 'calculation.item_changed', ['item' => $item->name, 'changes' => $changes]);
                }

                $kept[$item->id] = true;
            } else {
                $item = $calculation->items()->create([
                    ...$attributes,
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'cost' => 0,
                    'margin' => 0,
                    'is_accepted' => false,
                ]);

                ActivityLog::noteCalculation($calculation, 'calculation.item_added', ['item' => $item->name, 'price' => (float) $item->price]);
            }

            $byKey[$itemData['unique_id']] = $item;
        }

        foreach ($existing as $item) {
            if (isset($kept[$item->id])) {
                continue;
            }

            ActivityLog::noteCalculation($calculation, 'calculation.item_removed', ['item' => $item->name, 'price' => (float) $item->price]);
            $item->delete();
        }

        foreach ($items as $itemData) {
            $item = $byKey[$itemData['unique_id']] ?? null;

            if (! $item) {
                continue;
            }

            $parentKey = $itemData['parent_id'] ?? null;
            $parentId = $parentKey && isset($byKey[$parentKey]) ? $byKey[$parentKey]->id : null;

            if ($item->parent_id !== $parentId) {
                $item->update(['parent_id' => $parentId]);

                if ($item->wasRecentlyCreated === false) {
                    $parentChanged[] = $item->name;
                }
            }
        }

        foreach ($parentChanged as $name) {
            ActivityLog::noteCalculation($calculation, 'calculation.item_changed', ['item' => $name, 'changes' => ['item_parent' => []]]);
        }

        // The approved items are the ones the customer confirmed, so the totals follow them.
        $accepted = $calculation->items()->where('is_accepted', true)->get();
        $calculation->update([
            'total_price' => $accepted->sum(fn (CalculationItem $item) => (float) $item->price),
            'total_days' => $accepted->sum('days'),
        ]);
    }

    /**
     * @return array<string, array{from?: mixed, to?: mixed}>
     */
    private function describeItemChanges(CalculationItem $item): array
    {
        $changes = [];

        foreach (array_diff_key($item->getDirty(), ['sort_order' => true]) as $field => $new) {
            if ($field === 'description') {
                $changes[$field] = [];

                continue;
            }

            $old = $item->getOriginal($field);
            $changes[$field] = [
                'from' => $old instanceof \BackedEnum ? $old->value : $old,
                'to' => $new instanceof \BackedEnum ? $new->value : $new,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function assertUniqueKeys(array $items): void
    {
        $duplicateKeys = collect($items)->pluck('unique_id')->duplicates();

        if ($duplicateKeys->isNotEmpty()) {
            throw new InvalidArgumentException(sprintf(
                'Položky kalkulace musí mít unikátní unique_id. Duplicitní hodnoty: %s.',
                $duplicateKeys->unique()->implode(', '),
            ));
        }
    }
}
