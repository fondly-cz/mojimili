<?php

namespace App\Mcp\Tools;

use App\Models\Todo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-work-report')]
#[Title('Vykázat čas')]
#[Description('Vykáže odpracovaný čas k úkolu. Neuvedeš-li hodinovou sazbu, použije se tvoje sazba v projektu, případně výchozí sazba projektu. ID úkolů zjistíš nástrojem get-project.')]
class CreateWorkReportTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'todo_id' => 'required|integer|exists:todos,id',
            'minutes' => 'required|integer|min:1|max:1440',
            'date' => 'nullable|date',
            'hourly_rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'user_id' => 'nullable|integer|exists:users,id',
        ], [
            'todo_id.exists' => 'Úkol s tímto ID neexistuje. ID úkolů zjistíš nástrojem get-project.',
            'user_id.exists' => 'Uživatel s tímto ID v CRM neexistuje.',
        ]);

        $todo = Todo::with('todolist.project')->findOrFail($validated['todo_id']);
        $project = $todo->todolist->project;
        // A todo collects reports from any number of people; default to the caller.
        $workerId = $validated['user_id'] ?? $user->id;

        $report = $todo->workReports()->create([
            'user_id' => $workerId,
            'date' => $validated['date'] ?? now()->toDateString(),
            'minutes' => $validated['minutes'],
            'hourly_rate' => $validated['hourly_rate'] ?? $project->rateFor($workerId),
            'description' => $validated['description'] ?? null,
        ])->load('user:id,name');

        return Response::text(sprintf(
            "K úkolu \"%s\" vykázal(a) %s %d min (%s) za %s Kč/h = %s Kč bez DPH (work_report_id %d).\nDetail v CRM: %s",
            $todo->name,
            $report->user?->name ?? 'neznámý uživatel',
            $report->minutes,
            $report->date->toDateString(),
            $report->hourly_rate,
            number_format($report->amount, 2, ',', ' '),
            $report->id,
            route('projects.show', $project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'todo_id' => $schema->integer()
                ->description('ID úkolu, ke kterému se čas vykazuje.')
                ->required(),

            'minutes' => $schema->integer()
                ->description('Odpracovaný čas v minutách.')
                ->required(),

            'date' => $schema->string()
                ->description('Den, kdy se pracovalo, ve formátu YYYY-MM-DD. Výchozí je dnešek.'),

            'hourly_rate' => $schema->number()
                ->description('Vlastní hodinová sazba výkazu v Kč bez DPH. Výchozí je tvoje sazba v projektu, jinak sazba projektu.'),

            'description' => $schema->string()
                ->description('Popis odvedené práce.'),

            'user_id' => $schema->integer()
                ->description('ID uživatele CRM, který práci odvedl. Výchozí je přihlášený uživatel. K jednomu úkolu může vykazovat víc lidí a každý libovolný počet výkazů.'),
        ];
    }
}
