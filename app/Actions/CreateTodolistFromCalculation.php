<?php

namespace App\Actions;

use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Project;
use App\Models\Todolist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateTodolistFromCalculation
{
    /**
     * Turn selected calculation items into a todolist under the given project,
     * preserving the item hierarchy and the link back to each source item.
     *
     * @param  array<int, int>|null  $itemIds  Null means "every item the customer accepted".
     */
    public function handle(
        Calculation $calculation,
        Project $project,
        ?array $itemIds = null,
        ?string $name = null,
    ): Todolist {
        $items = $this->resolveItems($calculation, $itemIds);

        return DB::transaction(function () use ($calculation, $project, $items, $name) {
            $todolist = $project->todolists()->create([
                'name' => $name ?: ($calculation->customer_company ?: $calculation->customer_name),
                'calculation_id' => $calculation->id,
                'sort_order' => $project->todolists()->max('sort_order') + 1,
            ]);

            $this->createTodos($todolist, $items);

            return $todolist->load('todos');
        });
    }

    /**
     * Resolve which items become todos. Selecting a child without its parent would
     * orphan the child, so every selected item's ancestors are pulled in too.
     *
     * @param  array<int, int>|null  $itemIds
     * @return Collection<int, CalculationItem>
     */
    private function resolveItems(Calculation $calculation, ?array $itemIds): Collection
    {
        /** @var Collection<int, CalculationItem> $all */
        $all = $calculation->items()->get()->keyBy('id');

        if ($itemIds === null) {
            $selected = $all->filter(fn (CalculationItem $item) => $item->is_accepted);
        } else {
            $selected = $all->only($itemIds);
        }

        foreach ($selected as $item) {
            $parentId = $item->parent_id;

            while ($parentId !== null && ! $selected->has($parentId) && $all->has($parentId)) {
                $parent = $all->get($parentId);
                $selected->put($parent->id, $parent);
                $parentId = $parent->parent_id;
            }
        }

        // Keep the calculation's own ordering rather than selection order.
        return $selected
            ->sortBy(fn (CalculationItem $item) => [$item->sort_order, $item->id])
            ->values();
    }

    /**
     * Create the todos, then wire up parent/child in a second pass once every
     * source item has a corresponding todo id to point at.
     *
     * @param  Collection<int, CalculationItem>  $items
     */
    private function createTodos(Todolist $todolist, Collection $items): void
    {
        $created = [];

        foreach ($items as $index => $item) {
            $created[$item->id] = $todolist->todos()->create([
                'calculation_item_id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'days' => $item->days,
                'sort_order' => $index,
            ]);
        }

        foreach ($items as $item) {
            if ($item->parent_id && isset($created[$item->parent_id])) {
                $created[$item->id]->update([
                    'parent_id' => $created[$item->parent_id]->id,
                ]);
            }
        }
    }
}
