<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Company;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Page components are resolved per-route from the Vite manifest, which
        // only exists after a build; these tests assert props, not assets.
        $this->withoutVite();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/projects')->assertRedirect('/login');
    }

    public function test_it_lists_projects(): void
    {
        Project::factory()->create(['name' => 'Redesign webu']);

        $this->actingAs($this->manager())
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Redesign webu')
            );
    }

    public function test_it_filters_projects_by_search(): void
    {
        Project::factory()->create(['name' => 'Redesign webu']);
        Project::factory()->create(['name' => 'Migrace serveru']);

        $this->actingAs($this->manager())
            ->get('/projects?search=Migrace')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('projects.data', 1));
    }

    public function test_it_creates_a_project(): void
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->manager())->post('/projects', [
            'name' => 'Novy projekt',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $project = Project::firstOrFail();
        $response->assertRedirect("/projects/{$project->id}");

        $this->assertDatabaseHas('projects', [
            'name' => 'Novy projekt',
            'company_id' => $company->id,
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $this->actingAs($this->manager())
            ->post('/projects', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_it_rejects_an_unknown_status(): void
    {
        $this->actingAs($this->manager())
            ->post('/projects', ['name' => 'Projekt', 'status' => 'nonsense'])
            ->assertSessionHasErrors('status');
    }

    public function test_it_shows_a_project_with_its_todolists(): void
    {
        $project = Project::factory()->create();
        $todolist = Todolist::factory()->forProject($project)->create();
        Todo::factory()->forTodolist($todolist)->create();

        $this->actingAs($this->manager())
            ->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->has('project.todolists', 1)
                ->has('project.todolists.0.todos', 1)
                ->has('users')
                ->has('calculations')
            );
    }

    public function test_it_updates_a_project(): void
    {
        $project = Project::factory()->create(['name' => 'Puvodni']);

        $this->actingAs($this->manager())
            ->put("/projects/{$project->id}", ['name' => 'Zmeneny', 'status' => 'done'])
            ->assertRedirect("/projects/{$project->id}");

        $project->refresh();
        $this->assertSame('Zmeneny', $project->name);
        $this->assertSame('done', $project->status);
    }

    public function test_deleting_a_project_removes_its_todolists_and_todos(): void
    {
        $project = Project::factory()->create();
        $todolist = Todolist::factory()->forProject($project)->create();
        $todo = Todo::factory()->forTodolist($todolist)->create();

        $this->actingAs($this->manager())
            ->delete("/projects/{$project->id}")
            ->assertRedirect('/projects');

        $this->assertNull(Todolist::find($todolist->id));
        $this->assertNull(Todo::find($todo->id));
    }

    public function test_it_bulk_deletes_projects(): void
    {
        $projects = Project::factory()->count(3)->create();

        $this->actingAs($this->manager())
            ->post('/projects/bulk-delete', ['ids' => $projects->pluck('id')->all()])
            ->assertRedirect();

        $this->assertSame(0, Project::count());
    }

    public function test_it_returns_calculation_items_for_the_picker(): void
    {
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->create(['name' => 'Analyza']);

        $this->actingAs($this->manager())
            ->getJson("/api/calculations/{$calculation->id}/items")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Analyza']);
    }
}
