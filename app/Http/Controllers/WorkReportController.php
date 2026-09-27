<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\WorkReport;
use Illuminate\Http\Request;

class WorkReportController extends Controller
{
    public function store(Request $request, Todo $todo)
    {
        $validated = $request->validate(WorkReport::rules());

        $userId = $validated['user_id'] ?? auth()->id();

        $todo->workReports()->create([
            ...$validated,
            'user_id' => $userId,
            // No explicit rate means the person's rate in the project, then the project's default.
            'hourly_rate' => $validated['hourly_rate'] ?? $todo->todolist->project->rateFor($userId),
        ]);

        return back()->with('success', 'Čas byl vykázán.');
    }

    public function update(Request $request, WorkReport $workReport)
    {
        if ($workReport->isInvoiced()) {
            return back()->withErrors(['work_report' => 'Vyfakturovaný výkaz nelze upravit. Nejdřív ho odeberte z faktury.']);
        }

        $validated = $request->validate(WorkReport::rules(partial: true));

        if (array_key_exists('hourly_rate', $validated) && $validated['hourly_rate'] === null) {
            $validated['hourly_rate'] = $workReport->todo->todolist->project->rateFor(
                $validated['user_id'] ?? $workReport->user_id
            );
        }

        $workReport->update($validated);

        return back()->with('success', 'Výkaz byl upraven.');
    }

    public function destroy(WorkReport $workReport)
    {
        if ($workReport->isInvoiced()) {
            return back()->withErrors(['work_report' => 'Vyfakturovaný výkaz nelze smazat. Nejdřív ho odeberte z faktury.']);
        }

        $workReport->delete();

        return back()->with('success', 'Výkaz byl smazán.');
    }
}
