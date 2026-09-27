<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * Uninvoiced work reports, ready to be put on an invoice.
     */
    public function index(Request $request)
    {
        $reports = WorkReport::uninvoiced()
            ->with([
                'user:id,name',
                'todo:id,todolist_id,name',
                'todo.todolist:id,project_id,name',
                'todo.todolist.project:id,name,company_id',
                'todo.todolist.project.company:id,name',
            ])
            ->when($request->input('project_id'), fn ($q, $projectId) => $q->whereHas(
                'todo.todolist', fn ($l) => $l->where('project_id', $projectId)
            ))
            ->when($request->input('company_id'), fn ($q, $companyId) => $q->whereHas(
                'todo.todolist.project', fn ($p) => $p->where('company_id', $companyId)
            ))
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return inertia('Billing/Index', [
            'reports' => $reports,
            'filters' => $request->only(['project_id', 'company_id', 'user_id', 'date_from', 'date_to']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            // Recent invoices the selected reports can be added to.
            'invoices' => Invoice::latest('id')->limit(30)->get(['id', 'number', 'issued_at']),
        ]);
    }
}
