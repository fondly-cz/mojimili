<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-projects')]
#[Title('Seznam projektů')]
#[Description('Vypíše projekty v CRM, volitelně filtrované podle názvu, stavu nebo firmy. Detail včetně seznamů úkolů získáš nástrojem get-project.')]
class ListProjectsTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,on_hold,done,archived',
            'company_id' => 'nullable|integer|exists:companies,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $projects = Project::query()
            ->with('company:id,name')
            ->withCount('todolists')
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->latest()
            ->limit($validated['limit'] ?? 25)
            ->get();

        if ($projects->isEmpty()) {
            return Response::text('Nenalezen žádný projekt odpovídající zadání.');
        }

        return Response::text($projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'company_id' => $project->company_id,
            'company_name' => $project->company?->name,
            'todolists_count' => $project->todolists_count,
            'created_at' => $project->created_at->toDateString(),
            'url' => route('projects.show', $project),
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Hledaný výraz v názvu nebo popisu projektu.'),

            'status' => $schema->string()
                ->enum(['active', 'on_hold', 'done', 'archived'])
                ->description('Stav projektu: active = aktivní, on_hold = pozastavený, done = dokončený, archived = archivovaný.'),

            'company_id' => $schema->integer()
                ->description('Vypíše jen projekty této firmy (ID získáš z list-companies).'),

            'limit' => $schema->integer()
                ->description('Maximální počet vrácených projektů (1-100).')
                ->default(25),
        ];
    }
}
