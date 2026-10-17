<?php

namespace App\Http\Controllers;

use App\Actions\InvoiceWorkReports;
use App\Models\Invoice;
use App\Models\WorkReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()
            ->visibleTo($request->user())
            ->withTotals()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        return inertia('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    public function store(Request $request, InvoiceWorkReports $invoiceWorkReports)
    {
        $validated = $request->validate([
            ...$this->rules(),
            'work_report_ids' => 'required|array|min:1',
            'work_report_ids.*' => 'integer',
            'hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ]);

        $this->ensureReportsVisible($request, $validated['work_report_ids']);

        $invoice = DB::transaction(function () use ($validated, $invoiceWorkReports) {
            $invoice = Invoice::create([
                ...collect($validated)->except(['work_report_ids', 'hourly_rate'])->all(),
                'user_id' => auth()->id(),
            ]);

            $invoiceWorkReports->handle(
                $invoice,
                $validated['work_report_ids'],
                isset($validated['hourly_rate']) ? (string) $validated['hourly_rate'] : null,
            );

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Faktura byla vytvořena a výkazy označeny jako vyfakturované.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'user:id,name',
            'workReports.user:id,name',
            'workReports.project:id,name,company_id',
            'workReports.project.company:id,name',
        ]);

        return inertia('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $invoice->update($request->validate($this->rules()));

        return back()->with('success', 'Faktura byla upravena.');
    }

    public function destroy(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $invoice->workReports()->update(['invoice_id' => null]);
            $invoice->delete();
        });

        return redirect()->route('invoices.index')
            ->with('success', 'Faktura byla smazána a její výkazy vráceny k fakturaci.');
    }

    public function attach(Request $request, Invoice $invoice, InvoiceWorkReports $invoiceWorkReports)
    {
        $validated = $request->validate([
            'work_report_ids' => 'required|array|min:1',
            'work_report_ids.*' => 'integer',
            'hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ]);

        $this->ensureReportsVisible($request, $validated['work_report_ids']);

        $invoiceWorkReports->handle(
            $invoice,
            $validated['work_report_ids'],
            isset($validated['hourly_rate']) ? (string) $validated['hourly_rate'] : null,
        );

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Výkazy byly přidány do faktury.');
    }

    public function detach(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'work_report_ids' => 'required|array|min:1',
            'work_report_ids.*' => 'integer',
        ]);

        $invoice->workReports()->whereIn('id', $validated['work_report_ids'])->update(['invoice_id' => null]);

        return back()->with('success', 'Výkazy byly odebrány z faktury.');
    }

    /**
     * Reports of a project hidden from the user are treated as if they did not exist.
     *
     * @param  array<int, int>  $workReportIds
     *
     * @throws ValidationException
     */
    private function ensureReportsVisible(Request $request, array $workReportIds): void
    {
        if (WorkReport::idsHiddenFrom($request->user(), $workReportIds) !== []) {
            throw ValidationException::withMessages(['work_report_ids' => 'Některé vybrané výkazy neexistují.']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'number' => 'required|string|max:255',
            'url' => 'nullable|url|max:2048',
            'issued_at' => 'nullable|date',
            'note' => 'nullable|string',
        ];
    }
}
