<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\ListProjectsTool;
use App\Models\Activity;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAndCollaboratorRolesTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function todo(): Todo
    {
        return Todo::factory()->forTodolist(Todolist::factory()->create())->create(['estimated_minutes' => 600]);
    }

    public function test_the_roles_have_czech_labels(): void
    {
        $this->assertSame('Zákazník', UserRole::CUSTOMER->label());
        $this->assertSame('Spolupracovník', UserRole::COLLABORATOR->label());
    }

    public function test_a_customer_can_comment_but_not_report_time_or_edit(): void
    {
        $customer = $this->as(UserRole::CUSTOMER);
        $todo = $this->todo();

        $this->actingAs($customer)->post("/todos/{$todo->id}/comments", ['body' => 'Dotaz'])->assertSessionHasNoErrors();
        $this->assertSame(1, $todo->comments()->count());

        $this->actingAs($customer)->post("/todos/{$todo->id}/work-reports", ['date' => '2026-10-01', 'minutes' => 30])->assertForbidden();
        $this->actingAs($customer)->patch("/todos/{$todo->id}", ['is_done' => true])->assertForbidden();
        $this->actingAs($customer)->delete("/todos/{$todo->id}")->assertForbidden();
        $this->actingAs($customer)->post("/projects/{$todo->todolist->project_id}/todolists", ['name' => 'X'])->assertForbidden();
        $this->assertSame(0, WorkReport::count());
    }

    public function test_a_customer_can_create_a_todo_with_only_a_name_description_and_deadline(): void
    {
        $customer = $this->as(UserRole::CUSTOMER);
        $todolist = Todolist::factory()->create();

        $this->actingAs($customer)->post("/todolists/{$todolist->id}/todos", [
            'name' => 'Nový banner',
            'days' => 9,
            'due_date' => '2026-12-01',
        ])->assertSessionHasNoErrors();

        $todo = $todolist->todos()->first();
        $this->assertSame('Nový banner', $todo->name);
        $this->assertSame($customer->id, $todo->created_by_user_id);
        $this->assertSame(0, $todo->days);
    }

    public function test_a_customer_reads_projects_but_not_the_rest_of_the_crm(): void
    {
        $customer = $this->as(UserRole::CUSTOMER);
        $todo = $this->todo();
        WorkReport::factory()->forTodo($todo)->create();

        $this->withoutVite();
        $this->actingAs($customer)->get('/projects')->assertOk();
        $this->actingAs($customer)->get("/todos/{$todo->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('todo.work_reports', 0)->where('todo.spent_minutes', null));
        $this->actingAs($customer)->get('/')->assertRedirect('/projects');
        foreach (['/companies', '/calculations', '/billing', '/invoices', '/users'] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
    }

    public function test_a_collaborator_reports_their_own_time_at_the_project_rate(): void
    {
        $collaborator = $this->as(UserRole::COLLABORATOR);
        $other = $this->as(UserRole::MANAGER);
        $todo = $this->todo();
        $todo->todolist->project->update(['hourly_rate' => 800]);

        $this->actingAs($collaborator)->post("/todos/{$todo->id}/work-reports", [
            'date' => '2026-10-01', 'minutes' => 60, 'user_id' => $other->id, 'hourly_rate' => 99999,
        ])->assertSessionHasNoErrors();

        $report = WorkReport::firstOrFail();
        $this->assertSame($collaborator->id, $report->user_id);
        $this->assertSame('800.00', $report->hourly_rate);
    }

    public function test_a_collaborator_sees_only_their_own_entries_but_the_total_spent(): void
    {
        $collaborator = $this->as(UserRole::COLLABORATOR);
        $other = $this->as(UserRole::MANAGER);
        $todo = $this->todo();
        $mine = WorkReport::factory()->forTodo($todo)->create(['user_id' => $collaborator->id, 'minutes' => 60]);
        WorkReport::factory()->forTodo($todo)->create(['user_id' => $other->id, 'minutes' => 120]);

        $this->withoutVite();
        $this->actingAs($collaborator)->get("/todos/{$todo->id}")
            ->assertInertia(fn ($page) => $page
                ->has('todo.work_reports', 1)
                ->where('todo.work_reports.0.id', $mine->id)
                ->missing('todo.work_reports.0.hourly_rate')
                ->where('todo.spent_minutes', 180)
                ->where('todo.estimated_minutes', 600)
                ->missing('todo.todolist.project.hourly_rate'));
    }

    public function test_a_collaborator_cannot_touch_someone_elses_report_or_the_money_side(): void
    {
        $collaborator = $this->as(UserRole::COLLABORATOR);
        $other = $this->as(UserRole::MANAGER);
        $todo = $this->todo();
        $foreign = WorkReport::factory()->forTodo($todo)->create(['user_id' => $other->id]);

        $this->actingAs($collaborator)->patch("/work-reports/{$foreign->id}", ['minutes' => 5])->assertForbidden();
        $this->actingAs($collaborator)->delete("/work-reports/{$foreign->id}")->assertForbidden();
        foreach (['/companies', '/calculations', '/billing', '/invoices', '/services'] as $url) {
            $this->actingAs($collaborator)->get($url)->assertForbidden();
        }
        $this->assertModelExists($foreign);
    }

    public function test_the_history_hides_other_peoples_time_from_a_collaborator(): void
    {
        $collaborator = $this->as(UserRole::COLLABORATOR);
        $other = $this->as(UserRole::MANAGER);
        $todo = $this->todo();
        Activity::query()->delete();
        $this->actingAs($other)->post("/todos/{$todo->id}/work-reports", ['date' => '2026-10-01', 'minutes' => 30]);
        $this->actingAs($other)->post("/todos/{$todo->id}/comments", ['body' => 'Ahoj']);

        $this->actingAs($collaborator)->getJson("/todos/{$todo->id}/activity")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'comment.created');
    }

    public function test_these_roles_cannot_use_the_mcp_tools(): void
    {
        CrmServer::actingAs($this->as(UserRole::CUSTOMER))->tool(ListProjectsTool::class, [])->assertHasErrors();
        CrmServer::actingAs($this->as(UserRole::COLLABORATOR))->tool(ListProjectsTool::class, [])->assertHasErrors();
    }
}
