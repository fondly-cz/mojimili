<?php

namespace App\Http\Controllers;

use App\Models\Calculation;
use App\Models\Service;
use Illuminate\Http\Request;

class CalculationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);

        $calculations = Calculation::with('items')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_company', 'like', "%{$search}%");
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Calculations/Index', [
            'calculations' => $calculations,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function create()
    {
        return inertia('Calculations/Create', [
            'services' => Service::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'services' => 'required|array',
            'services.*.id' => 'required|exists:services,id',
            'services.*.unique_id' => 'required|string',
            'services.*.parent_id' => 'nullable|string',
            'services.*.is_required' => 'boolean',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.days' => 'required|integer|min:0',
            'services.*.payment_period' => 'required|string|in:once,monthly,yearly',
            'services.*.description' => 'nullable|string',
            'show_vat' => 'boolean',
            'company_id' => 'nullable|exists:companies,id',
            'company_employee_id' => 'nullable|exists:company_employees,id',
            'description' => 'nullable|string',
        ]);

        $calculation = Calculation::create([
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'customer_company' => $validated['customer_company'],
            'company_id' => $validated['company_id'] ?? null,
            'company_employee_id' => $validated['company_employee_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'note' => $validated['note'],
            'show_vat' => $request->boolean('show_vat'),
            'user_id' => auth()->id(),
            'total_price' => 0,
            'total_days' => 0,
        ]);

        $services = Service::whereIn('id', collect($validated['services'])->pluck('id'))->get();
        $createdItems = [];

        // Save items first (flat)
        foreach ($validated['services'] as $index => $itemData) {
            $service = $services->firstWhere('id', $itemData['id']);
            if (! $service) {
                continue;
            }

            $item = $calculation->items()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'description' => $itemData['description'] ?? $service->description,
                'cost' => 0, // Costs are now individual per calculation
                'margin' => 0,
                'price' => $itemData['price'],
                'days' => $itemData['days'],
                'payment_period' => $itemData['payment_period'],
                'is_accepted' => false,
                'is_required' => $itemData['is_required'] ?? false,
                'sort_order' => $index,
            ]);

            $createdItems[$itemData['unique_id']] = $item;
        }

        // Apply hierarchy based on parent_id
        foreach ($validated['services'] as $itemData) {
            if (! empty($itemData['parent_id']) && isset($createdItems[$itemData['parent_id']])) {
                /** @var \App\Models\CalculationItem $item */
                $item = $createdItems[$itemData['unique_id']];
                /** @var \App\Models\CalculationItem $parentItem */
                $parentItem = $createdItems[$itemData['parent_id']];
                $item->update(['parent_id' => $parentItem->id]);
            }
        }

        return redirect()->route('calculations.show', $calculation);
    }

    public function show(Calculation $calculation)
    {
        return inertia('Calculations/Show', [
            'calculation' => $calculation->load('items'),
        ]);
    }

    public function showPublic(string $token)
    {
        $calculation = Calculation::where('access_token', $token)->firstOrFail();

        return inertia('Calculations/Show', [
            'calculation' => $calculation->load('items'),
            'is_public' => true,
        ]);
    }

    public function acceptPublic(Request $request, string $token)
    {
        $calculation = Calculation::where('access_token', $token)->firstOrFail();

        $validated = $request->validate([
            'accepted_items' => 'required|array',
            'accepted_items.*' => 'exists:calculation_items,id',
        ]);

        $acceptedIds = collect($validated['accepted_items']);

        $totalPrice = 0;
        $totalDays = 0;

        foreach ($calculation->items as $item) {
            /** @var \App\Models\CalculationItem $item */
            $isAccepted = $acceptedIds->contains($item->id);
            $item->update(['is_accepted' => $isAccepted]);

            if ($isAccepted) {
                $totalPrice += $item->price;
                $totalDays += $item->days;
            }
        }

        $calculation->update([
            'total_price' => $totalPrice,
            'total_days' => $totalDays,
            'status' => 'confirmed',
        ]);

        return back()->with('success', 'Kalkulace byla úspěšně potvrzena. Děkujeme!');
    }

    public function edit(Calculation $calculation)
    {
        return inertia('Calculations/Edit', [
            'calculation' => $calculation->load('items.service'),
            'services' => Service::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, Calculation $calculation)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'services' => 'required|array',
            'services.*.id' => 'required|exists:services,id',
            'services.*.unique_id' => 'required|string',
            'services.*.parent_id' => 'nullable|string',
            'services.*.is_required' => 'boolean',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.days' => 'required|integer|min:0',
            'services.*.payment_period' => 'required|string|in:once,monthly,yearly',
            'services.*.description' => 'nullable|string',
            'show_vat' => 'boolean',
            'company_id' => 'nullable|exists:companies,id',
            'company_employee_id' => 'nullable|exists:company_employees,id',
            'description' => 'nullable|string',
        ]);

        $calculation->update([
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'customer_company' => $validated['customer_company'],
            'company_id' => $validated['company_id'] ?? null,
            'company_employee_id' => $validated['company_employee_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'note' => $validated['note'],
            'show_vat' => $request->boolean('show_vat'),
        ]);

        // Delete old items and recreate
        $calculation->items()->delete();

        $services = Service::whereIn('id', collect($validated['services'])->pluck('id'))->get();
        $createdItems = [];

        foreach ($validated['services'] as $index => $itemData) {
            $service = $services->firstWhere('id', $itemData['id']);
            if (! $service) {
                continue;
            }

            $item = $calculation->items()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'description' => $itemData['description'] ?? $service->description,
                'cost' => 0,
                'margin' => 0,
                'price' => $itemData['price'],
                'days' => $itemData['days'],
                'payment_period' => $itemData['payment_period'],
                'is_accepted' => false,
                'is_required' => $itemData['is_required'] ?? false,
                'sort_order' => $index,
            ]);

            $createdItems[$itemData['unique_id']] = $item;
        }

        foreach ($validated['services'] as $itemData) {
            if (! empty($itemData['parent_id']) && isset($createdItems[$itemData['parent_id']])) {
                /** @var \App\Models\CalculationItem $item */
                $item = $createdItems[$itemData['unique_id']];
                /** @var \App\Models\CalculationItem $parentItem */
                $parentItem = $createdItems[$itemData['parent_id']];
                $item->update(['parent_id' => $parentItem->id]);
            }
        }

        return redirect()->route('calculations.show', $calculation)->with('success', 'Kalkulace byla úspěšně upravena.');
    }

    public function destroy(Calculation $calculation)
    {
        $calculation->delete();

        return redirect()->route('calculations.index')->with('success', 'Kalkulace byla smazána.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:calculations,id',
        ]);

        Calculation::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', 'Vybrané kalkulace byly smazány.');
    }
}
