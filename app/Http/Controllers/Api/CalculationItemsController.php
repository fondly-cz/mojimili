<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\CalculationItem;

class CalculationItemsController extends Controller
{
    /**
     * Items of a calculation, for the "todolist from calculation" picker.
     */
    public function __invoke(Calculation $calculation)
    {
        return $calculation->items()->get()->map(fn (CalculationItem $item) => [
            'id' => $item->id,
            'parent_id' => $item->parent_id,
            'name' => $item->name,
            'description' => $item->description,
            'days' => $item->days,
            'is_accepted' => $item->is_accepted,
        ]);
    }
}
