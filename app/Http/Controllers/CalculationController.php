<?php

namespace App\Http\Controllers;

use App\Actions\SaveCalculation;
use App\Mail\CalculationConfirmed;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\CalculationView;
use App\Models\Project;
use App\Models\Service;
use App\Support\ClientIp;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

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

    public function store(Request $request, SaveCalculation $saveCalculation)
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
            'created_at' => 'nullable|date',
            'valid_days' => 'nullable|integer|min:1|max:365',
        ]);

        $validated['show_vat'] = $request->boolean('show_vat');

        $calculation = $saveCalculation->create($validated, auth()->id());

        return redirect()->route('calculations.show', $calculation);
    }

    public function show(Calculation $calculation)
    {
        return inertia('Calculations/Show', [
            'calculation' => $calculation->load('items'),
            // For the "turn items into a todolist" modal.
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'views' => $this->viewLog($calculation),
        ]);
    }

    public function showPublic(Request $request, string $token)
    {
        $calculation = Calculation::where('access_token', $token)->firstOrFail();

        $this->logView($request, $calculation);

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
            /** @var CalculationItem $item */
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

        $author = $calculation->user;

        if ($author?->email) {
            Mail::to($author->email)->send(new CalculationConfirmed($calculation->fresh('items')));
        }

        return back()->with('success', 'Kalkulace byla úspěšně potvrzena. Děkujeme!');
    }

    public function unconfirm(Calculation $calculation)
    {
        $calculation->update(['status' => 'draft']);

        return back()->with('success', 'Potvrzení kalkulace bylo zrušeno. Zákazník ji nyní může znovu upravit a potvrdit.');
    }

    public function edit(Calculation $calculation)
    {
        return inertia('Calculations/Edit', [
            'calculation' => $calculation->load('items.service'),
            'services' => Service::where('is_active', true)->get(),
            'views' => $this->viewLog($calculation),
        ]);
    }

    public function update(Request $request, Calculation $calculation, SaveCalculation $saveCalculation)
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
            'created_at' => 'required|date',
            'valid_days' => 'required|integer|min:1|max:365',
        ]);

        $validated['show_vat'] = $request->boolean('show_vat');

        $saveCalculation->update($calculation, $validated);

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

    private function logView(Request $request, Calculation $calculation): void
    {
        // Inertia partial reloads and prefetches are not new visits.
        if ($request->header('X-Inertia-Partial-Data') || $request->header('Purpose') === 'prefetch') {
            return;
        }

        $userAgent = $request->userAgent();

        $calculation->views()->create([
            'user_id' => $request->user()?->id,
            'ip_address' => ClientIp::resolve($request),
            'country' => substr((string) $request->header('CF-IPCountry'), 0, 2) ?: null,
            'user_agent' => $userAgent,
            'viewed_at' => now(),
            ...UserAgent::parse($userAgent),
        ]);
    }

    /**
     * @return Collection<int, CalculationView>
     */
    private function viewLog(Calculation $calculation)
    {
        return $calculation->views()->with('user:id,name')->latest('viewed_at')->latest('id')->get();
    }
}
