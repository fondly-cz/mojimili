<?php

namespace App\Http\Controllers;

use App\Models\Calculation;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);

        $projects = Project::with('company')
            ->withCount('todolists')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function create()
    {
        return inertia('Projects/Create', [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $project = Project::create([
            ...$validated,
            'user_id' => auth()->id(),
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projekt byl vytvořen.');
    }

    public function show(Project $project)
    {
        $project->load([
            'company',
            'companyEmployee',
            'user',
            'todolists.calculation:id,customer_name,customer_company',
            // The list shows only a summary; the thread lives on the todo's detail page.
            'todolists.todos' => fn ($query) => $query->withCount('comments'),
            'todolists.todos.assignee:id,name',
            'todolists.todos.workReports.user:id,name',
            'todolists.todos.workReports.invoice:id,number,url',
            'userRates:id,name',
        ]);

        return inertia('Projects/Show', [
            'project' => $project,
            'users' => User::orderBy('name')->get(['id', 'name']),
            // Calculations that can still be turned into a todolist for this project.
            'calculations' => Calculation::query()
                ->when($project->company_id, fn ($q) => $q->where('company_id', $project->company_id))
                ->latest()
                ->limit(50)
                ->get(['id', 'customer_name', 'customer_company', 'status', 'created_at']),
        ]);
    }

    public function edit(Project $project)
    {
        return inertia('Projects/Edit', [
            'project' => $project->load(['company', 'userRates:id,name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            ...$this->rules(),
            'user_rates' => 'sometimes|array',
            'user_rates.*.user_id' => 'required|distinct|exists:users,id',
            'user_rates.*.hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ]);

        DB::transaction(function () use ($project, $validated) {
            $project->update(collect($validated)->except('user_rates')->all());

            if (array_key_exists('user_rates', $validated)) {
                // An empty rate removes the person's override.
                $project->userRates()->sync(collect($validated['user_rates'])
                    ->filter(fn ($rate) => $rate['hourly_rate'] !== null && $rate['hourly_rate'] !== '')
                    ->mapWithKeys(fn ($rate) => [$rate['user_id'] => ['hourly_rate' => $rate['hourly_rate']]])
                    ->all());
            }
        });

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projekt byl upraven.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Projekt byl smazán.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:projects,id',
        ]);

        // Model deletes, so each project also removes its todos' attachment files.
        Project::whereIn('id', $validated['ids'])->get()->each->delete();

        return back()->with('success', 'Vybrané projekty byly smazány.');
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'company_employee_id' => 'nullable|exists:company_employees,id',
            'status' => 'nullable|string|in:active,on_hold,done,archived',
            'hourly_rate' => 'nullable|numeric|min:0|max:99999999',
        ];
    }
}
