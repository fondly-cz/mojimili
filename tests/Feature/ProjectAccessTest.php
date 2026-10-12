<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTodoCommentTool;
use App\Mcp\Tools\CreateWorkReportTool;
use App\Mcp\Tools\GetInvoiceTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListUninvoicedWorkReportsTool;
use App\Mcp\Tools\UpdateProjectMembersTool;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\Todolist;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $everyoneInEveryProject = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    private function todoIn(Project $project): Todo
    {
        return Todo::factory()->forTodolist(Todolist::factory()->create(['project_id' => $project->id]))->create();
    }

    public function test_the_project_list_shows_only_the_projects_a_person_is_assigned_to(): void
    {
        $manager = $this->manager();
        Project::factory()->create(['name' => 'Cizí projekt']);
        Project::factory()->create(['name' => 'Můj projekt'])->members()->attach($manager);

        $this->actingAs($manager)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Můj projekt'));
    }

    public function test_an_administrator_sees_every_project(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->get('/projects')->assertInertia(fn ($page) => $page->has('projects.data', 1));
        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk();
    }

    public function test_everything_in_a_project_is_missing_for_someone_outside_it(): void
    {
        $outsider = $this->manager();
        $project = Project::factory()->create();
        $todo = $this->todoIn($project);
        $comment = TodoComment::factory()->create(['todo_id' => $todo->id]);
        $report = WorkReport::factory()->create(['todo_id' => $todo->id]);
        $document = ProjectDocument::factory()->create(['project_id' => $project->id]);

        $this->actingAs($outsider);

        $this->get("/projects/{$project->id}")->assertNotFound();
        $this->get("/projects/{$project->id}/edit")->assertNotFound();
        $this->patch("/projects/{$project->id}", ['name' => 'Přepsáno'])->assertNotFound();
        $this->delete("/projects/{$project->id}")->assertNotFound();
        $this->post("/projects/{$project->id}/members", ['user_id' => $outsider->id])->assertNotFound();
        $this->post("/projects/{$project->id}/todolists", ['name' => 'Seznam'])->assertNotFound();
        $this->post("/todolists/{$todo->todolist_id}/todos", ['name' => 'Úkol'])->assertNotFound();
        $this->get("/todos/{$todo->id}")->assertNotFound();
        $this->patch("/todos/{$todo->id}", ['is_done' => true])->assertNotFound();
        $this->post("/todos/{$todo->id}/comments", ['body' => 'Ahoj'])->assertNotFound();
        $this->delete("/todo-comments/{$comment->id}")->assertNotFound();
        $this->delete("/work-reports/{$report->id}")->assertNotFound();
        $this->delete("/project-documents/{$document->id}")->assertNotFound();

        $this->post('/projects/bulk-delete', ['ids' => [$project->id]]);

        $this->assertSame('active', $project->fresh()->status);
        $this->assertNotSame('Přepsáno', $project->fresh()->name);
        $this->assertFalse($todo->fresh()->is_done);
        $this->assertSame(0, $project->members()->count());
    }

    public function test_a_member_works_with_the_project_as_before(): void
    {
        $member = $this->manager();
        $project = Project::factory()->create();
        $project->members()->attach($member);
        $todo = $this->todoIn($project);

        $this->actingAs($member)->get("/projects/{$project->id}")->assertOk();
        $this->actingAs($member)->get("/todos/{$todo->id}")->assertOk();
        $this->actingAs($member)->patch("/todos/{$todo->id}", ['is_done' => true])->assertSessionHasNoErrors();

        $this->assertTrue($todo->fresh()->is_done);
    }

    public function test_whoever_removes_themselves_lands_on_the_project_list(): void
    {
        $member = $this->manager();
        $project = Project::factory()->create();
        $project->members()->attach($member);

        $this->actingAs($member)
            ->from("/projects/{$project->id}")
            ->delete("/projects/{$project->id}/members/{$member->id}")
            ->assertRedirect('/projects');

        $this->actingAs($member)->get("/projects/{$project->id}")->assertNotFound();
    }

    public function test_a_todo_can_be_assigned_only_to_a_person_from_the_project(): void
    {
        $member = $this->manager();
        $colleague = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach([$member->id, $colleague->id]);
        $todo = $this->todoIn($project);

        $this->actingAs($member)
            ->patch("/todos/{$todo->id}", ['assigned_user_id' => $outsider->id])
            ->assertSessionHasErrors('assigned_user_id');
        $this->assertNull($todo->fresh()->assigned_user_id);

        $this->actingAs($member)
            ->patch("/todos/{$todo->id}", ['assigned_user_id' => $colleague->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($colleague->id, $todo->fresh()->assigned_user_id);

        $this->actingAs($member)
            ->post("/todolists/{$todo->todolist_id}/todos", ['name' => 'Další', 'assigned_user_id' => $outsider->id])
            ->assertSessionHasErrors('assigned_user_id');

        $response = CrmServer::actingAs($member)->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'assigned_user_id' => $outsider->id,
        ]);
        $response->assertHasErrors();
        $this->assertSame($colleague->id, $todo->fresh()->assigned_user_id);
    }

    public function test_billing_and_invoices_leave_out_work_from_hidden_projects(): void
    {
        $manager = $this->manager();
        $mine = Project::factory()->create();
        $mine->members()->attach($manager);
        $myReport = WorkReport::factory()->create(['todo_id' => $this->todoIn($mine)->id]);
        $hiddenReport = WorkReport::factory()->create(['todo_id' => $this->todoIn(Project::factory()->create())->id]);

        $this->actingAs($manager)
            ->get('/billing')
            ->assertInertia(fn ($page) => $page
                ->has('reports', 1)
                ->where('reports.0.id', $myReport->id)
                ->has('projects', 1));

        $this->actingAs($manager)
            ->post('/invoices', ['number' => 'F-1', 'work_report_ids' => [$myReport->id, $hiddenReport->id]])
            ->assertSessionHasErrors('work_report_ids');
        $this->assertSame(0, Invoice::count());

        $hiddenInvoice = Invoice::factory()->create();
        $hiddenReport->update(['invoice_id' => $hiddenInvoice->id]);
        $myInvoice = Invoice::factory()->create();
        $myReport->update(['invoice_id' => $myInvoice->id]);

        $this->actingAs($manager)
            ->get('/invoices')
            ->assertInertia(fn ($page) => $page->has('invoices.data', 1)->where('invoices.data.0.id', $myInvoice->id));
        $this->actingAs($manager)->get("/invoices/{$hiddenInvoice->id}")->assertNotFound();
        $this->actingAs($manager)->get("/invoices/{$myInvoice->id}")->assertOk();

        CrmServer::actingAs($manager)->tool(GetInvoiceTool::class, ['id' => $hiddenInvoice->id])->assertHasErrors();
        CrmServer::actingAs($manager)->tool(ListUninvoicedWorkReportsTool::class, [])
            ->assertSee('Žádné nevyfakturované výkazy');
    }

    public function test_mcp_tools_hide_a_project_from_someone_outside_it(): void
    {
        $outsider = $this->manager();
        $project = Project::factory()->create(['name' => 'Tajný projekt']);
        $todo = $this->todoIn($project);

        CrmServer::actingAs($outsider)->tool(ListProjectsTool::class, [])->assertDontSee('Tajný projekt');
        CrmServer::actingAs($outsider)->tool(GetProjectTool::class, ['id' => $project->id])
            ->assertHasErrors()
            ->assertDontSee('Tajný projekt');
        CrmServer::actingAs($outsider)->tool(UpdateTodoTool::class, ['id' => $todo->id, 'is_done' => true])->assertHasErrors();
        CrmServer::actingAs($outsider)->tool(CreateTodoCommentTool::class, ['todo_id' => $todo->id, 'body' => 'Ahoj'])->assertHasErrors();
        CrmServer::actingAs($outsider)->tool(CreateWorkReportTool::class, ['todo_id' => $todo->id, 'minutes' => 30])->assertHasErrors();
        CrmServer::actingAs($outsider)->tool(UpdateProjectMembersTool::class, [
            'project_id' => $project->id,
            'add_user_ids' => [$outsider->id],
        ])->assertHasErrors();

        $this->assertFalse($todo->fresh()->is_done);
        $this->assertSame(0, $todo->comments()->count());
        $this->assertSame(0, $todo->workReports()->count());
        $this->assertSame(0, $project->members()->count());
    }

    public function test_the_mcp_tool_assigns_and_removes_people(): void
    {
        $member = $this->manager();
        $jana = User::factory()->create(['name' => 'Jana Nováková']);
        $petr = User::factory()->create(['name' => 'Petr Starý']);
        $project = Project::factory()->create();
        $project->members()->attach([$member->id, $petr->id]);

        $response = CrmServer::actingAs($member)->tool(UpdateProjectMembersTool::class, [
            'project_id' => $project->id,
            'add_user_ids' => [$jana->id, $member->id],
            'remove_user_ids' => [$petr->id],
        ]);

        $response->assertHasNoErrors()->assertSee('Jana Nováková')->assertDontSee('Petr Starý');
        $this->assertEqualsCanonicalizing([$member->id, $jana->id], $project->members()->pluck('users.id')->all());

        CrmServer::actingAs($member)->tool(UpdateProjectMembersTool::class, ['project_id' => $project->id])->assertHasErrors();
    }

    public function test_a_project_created_over_mcp_is_visible_to_its_creator(): void
    {
        $manager = $this->manager();

        CrmServer::actingAs($manager)->tool(CreateProjectTool::class, ['name' => 'Nový projekt'])->assertHasNoErrors();

        $this->assertSame([$manager->id], Project::firstOrFail()->members()->pluck('users.id')->all());
        CrmServer::actingAs($manager)->tool(ListProjectsTool::class, [])->assertSee('Nový projekt');
    }
}
