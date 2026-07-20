<?php

namespace App\Http\Controllers;

use App\Models\Calculation;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

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
            'todolists.todos.assignee:id,name',
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
            'project' => $project->load('company'),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate($this->rules());

        $project->update($validated);

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

        Project::whereIn('id', $validated['ids'])->delete();

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
        ];
    }
}
