<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTodolistFromCalculationTool;
use App\Mcp\Tools\CreateTodolistTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Company;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectToolsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_it_lists_projects(): void
    {
        Project::factory()->create(['name' => 'Redesign webu']);
        Project::factory()->create(['name' => 'Migrace serveru']);

        $response = CrmServer::actingAs($this->manager())->tool(ListProjectsTool::class, []);

        $response->assertOk()->assertSee('Redesign webu')->assertSee('Migrace serveru');
    }

    public function test_it_filters_projects_by_status(): void
    {
        Project::factory()->create(['name' => 'Aktivni projekt']);
        Project::factory()->archived()->create(['name' => 'Archivovany projekt']);

        $response = CrmServer::actingAs($this->manager())->tool(ListProjectsTool::class, [
            'status' => 'archived',
        ]);

        $response->assertOk()
            ->assertSee('Archivovany projekt')
            ->assertDontSee('Aktivni projekt');
    }

    public function test_a_user_without_a_role_is_denied(): void
    {
        $user = User::factory()->create(['role' => null]);

        $response = CrmServer::actingAs($user)->tool(ListProjectsTool::class, []);

        $response->assertHasErrors();
    }

    public function test_it_creates_a_project(): void
    {
        $company = Company::factory()->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateProjectTool::class, [
            'name' => 'Novy projekt',
            'company_id' => $company->id,
        ]);

        $response->assertOk()->assertSee('Novy projekt');

        $this->assertDatabaseHas('projects', [
            'name' => 'Novy projekt',
            'company_id' => $company->id,
            'status' => 'active',
        ]);
    }

    public function test_it_rejects_an_unknown_company(): void
    {
        $response = CrmServer::actingAs($this->manager())->tool(CreateProjectTool::class, [
            'name' => 'Novy projekt',
            'company_id' => 9999,
        ]);

        $response->assertHasErrors();
    }

    public function test_it_updates_only_the_supplied_fields(): void
    {
        $project = Project::factory()->create(['name' => 'Puvodni', 'description' => 'Popis zustava']);

        $response = CrmServer::actingAs($this->manager())->tool(UpdateProjectTool::class, [
            'id' => $project->id,
            'status' => 'done',
        ]);

        $response->assertOk();

        $project->refresh();
        $this->assertSame('done', $project->status);
        $this->assertSame('Puvodni', $project->name);
        $this->assertSame('Popis zustava', $project->description);
    }

    public function test_updating_with_no_changes_is_an_error(): void
    {
        $project = Project::factory()->create();

        $response = CrmServer::actingAs($this->manager())->tool(UpdateProjectTool::class, [
            'id' => $project->id,
        ]);

        $response->assertHasErrors();
    }

    public function test_it_returns_a_project_with_its_todos(): void
    {
        $project = Project::factory()->create(['name' => 'Projekt s ukoly']);
        $todolist = Todolist::factory()->forProject($project)->create(['name' => 'Etapa 1']);
        Todo::factory()->forTodolist($todolist)->create(['name' => 'Analyza']);

        $response = CrmServer::actingAs($this->manager())->tool(GetProjectTool::class, [
            'id' => $project->id,
        ]);

        $response->assertOk()
            ->assertSee('Projekt s ukoly')
            ->assertSee('Etapa 1')
            ->assertSee('Analyza');
    }

    public function test_it_creates_a_todolist_with_nested_todos(): void
    {
        $project = Project::factory()->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateTodolistTool::class, [
            'project_id' => $project->id,
            'name' => 'Etapa 1',
            'todos' => [
                ['key' => 'a', 'name' => 'Rodic'],
                ['key' => 'b', 'parent_key' => 'a', 'name' => 'Potomek'],
            ],
        ]);

        $response->assertOk()->assertSee('Etapa 1');

        $todolist = Todolist::where('project_id', $project->id)->firstOrFail();
        $parent = $todolist->todos->firstWhere('name', 'Rodic');
        $child = $todolist->todos->firstWhere('name', 'Potomek');

        $this->assertNull($parent->parent_id);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_it_builds_a_todolist_from_accepted_calculation_items(): void
    {
        $project = Project::factory()->create();
        $calculation = Calculation::factory()->confirmed()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create(['name' => 'Odsouhlaseno']);
        CalculationItem::factory()->forCalculation($calculation)->create(['name' => 'Neodsouhlaseno']);

        $response = CrmServer::actingAs($this->manager())->tool(CreateTodolistFromCalculationTool::class, [
            'calculation_id' => $calculation->id,
            'project_id' => $project->id,
        ]);

        $response->assertOk()->assertSee('Odsouhlaseno');

        $todolist = Todolist::where('project_id', $project->id)->firstOrFail();
        $this->assertCount(1, $todolist->todos);
        $this->assertSame('Odsouhlaseno', $todolist->todos->first()->name);
    }

    public function test_it_creates_the_project_when_only_a_name_is_given(): void
    {
        $company = Company::factory()->create();
        $calculation = Calculation::factory()->forCompany($company)->confirmed()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateTodolistFromCalculationTool::class, [
            'calculation_id' => $calculation->id,
            'project_name' => 'Zbrusu novy projekt',
        ]);

        $response->assertOk()->assertSee('Zbrusu novy projekt');

        // The new project inherits the calculation's company.
        $this->assertDatabaseHas('projects', [
            'name' => 'Zbrusu novy projekt',
            'company_id' => $company->id,
        ]);
    }

    public function test_it_refuses_a_calculation_with_nothing_accepted(): void
    {
        $project = Project::factory()->create();
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateTodolistFromCalculationTool::class, [
            'calculation_id' => $calculation->id,
            'project_id' => $project->id,
        ]);

        // Silently producing an empty list would look like success to the model.
        $response->assertHasErrors();
        $this->assertSame(0, Todolist::count());
    }

    public function test_it_refuses_items_from_another_calculation(): void
    {
        $project = Project::factory()->create();
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->create();
        $foreign = CalculationItem::factory()->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateTodolistFromCalculationTool::class, [
            'calculation_id' => $calculation->id,
            'project_id' => $project->id,
            'item_ids' => [$foreign->id],
        ]);

        $response->assertHasErrors();
        $this->assertSame(0, Todolist::count());
    }

    public function test_it_requires_a_project(): void
    {
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateTodolistFromCalculationTool::class, [
            'calculation_id' => $calculation->id,
        ]);

        $response->assertHasErrors();
    }

    public function test_it_marks_a_todo_as_done(): void
    {
        $todo = Todo::factory()->create(['name' => 'Hotovy ukol']);

        $response = CrmServer::actingAs($this->manager())->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'is_done' => true,
        ]);

        $response->assertOk()->assertSee('Hotovy ukol');

        $todo->refresh();
        $this->assertTrue($todo->is_done);
        $this->assertNotNull($todo->completed_at);
    }

    public function test_reopening_a_todo_clears_the_completion_time(): void
    {
        $todo = Todo::factory()->done()->create();

        CrmServer::actingAs($this->manager())->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'is_done' => false,
        ])->assertOk();

        $todo->refresh();
        $this->assertFalse($todo->is_done);
        $this->assertNull($todo->completed_at);
    }

    public function test_it_assigns_a_todo(): void
    {
        $todo = Todo::factory()->create();
        $assignee = User::factory()->create(['role' => UserRole::MANAGER]);

        CrmServer::actingAs($this->manager())->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'assigned_user_id' => $assignee->id,
        ])->assertOk();

        $this->assertSame($assignee->id, $todo->refresh()->assigned_user_id);
    }
}
