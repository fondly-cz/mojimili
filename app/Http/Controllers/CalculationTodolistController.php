<?php

namespace App\Http\Controllers;

use App\Actions\CreateTodolistFromCalculation;
use App\Models\Calculation;
use App\Models\Project;
use Illuminate\Http\Request;

class CalculationTodolistController extends Controller
{
    public function store(
        Request $request,
        Calculation $calculation,
        CreateTodolistFromCalculation $createTodolist,
    ) {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'project_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:calculation_items,id',
        ]);

        if (empty($validated['project_id']) && empty($validated['project_name'])) {
            return back()->withErrors([
                'project_id' => 'Vyber existující projekt, nebo zadej název nového.',
            ]);
        }

        $itemIds = $validated['item_ids'] ?? null;

        if ($itemIds !== null) {
            // Items from another calculation would silently produce an empty list.
            $ownedIds = $calculation->items()->whereIn('id', $itemIds)->pluck('id');

            if ($ownedIds->count() !== count($itemIds)) {
                return back()->withErrors([
                    'item_ids' => 'Některé vybrané položky nepatří do této kalkulace.',
                ]);
            }
        } elseif (! $calculation->items()->where('is_accepted', true)->exists()) {
            return back()->withErrors([
                'item_ids' => 'Kalkulace zatím nemá žádné odsouhlasené položky. Vyber položky ručně.',
            ]);
        }

        $project = ! empty($validated['project_id'])
            ? Project::findOrFail($validated['project_id'])
            : Project::create([
                'name' => $validated['project_name'],
                'company_id' => $calculation->company_id,
                'company_employee_id' => $calculation->company_employee_id,
                'user_id' => auth()->id(),
                'status' => 'active',
            ]);

        $createTodolist->handle($calculation, $project, $itemIds, $validated['name'] ?? null);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Seznam úkolů byl vytvořen z kalkulace.');
    }
}
