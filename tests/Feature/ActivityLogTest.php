<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    private function lastEvents(): array
    {
        return Activity::orderBy('id')->pluck('event')->all();
    }

    public function test_it_logs_the_life_of_a_todo(): void
    {
        $user = $this->manager();
        $list = Todolist::factory()->create();
        Activity::query()->delete();

        $this->actingAs($user)->post("/todolists/{$list->id}/todos", ['name' => 'Napsat text'])->assertSessionHasNoErrors();
        $todo = Todo::firstOrFail();
        $this->actingAs($user)->patch("/todos/{$todo->id}", ['name' => 'Napsat článek', 'due_date' => '2026-12-01']);
        $this->actingAs($user)->patch("/todos/{$todo->id}", ['is_done' => true]);
        $this->actingAs($user)->post("/todos/{$todo->id}/comments", ['body' => 'Hotovo']);
        $this->actingAs($user)->post("/todos/{$todo->id}/work-reports", ['date' => '2026-10-01', 'minutes' => 90]);
        $this->actingAs($user)->delete("/todos/{$todo->id}");

        $this->assertSame([
            'todo.created', 'todo.updated', 'todo.completed', 'comment.created', 'work_report.created', 'todo.deleted',
        ], $this->lastEvents());
        $this->assertSame($user->id, Activity::first()->user_id);
        $this->assertStringContainsString('název: Napsat text → Napsat článek', Activity::where('event', 'todo.updated')->first()->text);
        $this->assertStringContainsString('1 h 30 min', Activity::where('event', 'work_report.created')->first()->text);
    }

    public function test_a_sort_order_only_change_is_not_logged(): void
    {
        $todo = Todo::factory()->create();
        Activity::query()->delete();

        $todo->update(['sort_order' => 5]);

        $this->assertSame(0, Activity::count());
    }

    public function test_it_logs_member_changes_and_archiving(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $person = $this->manager();
        $project = Project::factory()->create();
        $list = Todolist::factory()->forProject($project)->create();
        $project->members()->detach($person);
        Activity::query()->delete();

        $this->actingAs($admin)->post("/projects/{$project->id}/members", ['user_id' => $person->id, 'todolist_ids' => [$list->id]]);
        $this->actingAs($admin)->post("/todolists/{$list->id}/archive");
        $this->actingAs($admin)->delete("/projects/{$project->id}/members/{$person->id}");

        $this->assertSame(['project.member_added', 'todolist.archived', 'project.member_removed'], $this->lastEvents());
        $this->assertStringContainsString("vidí seznamy: {$list->name}", Activity::first()->text);
    }

    public function test_the_mcp_actor_is_recorded(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();
        Activity::query()->delete();

        CrmServer::actingAs($user)->tool(UpdateTodoTool::class, ['id' => $todo->id, 'is_done' => true])->assertOk();

        $this->assertSame($user->id, Activity::where('event', 'todo.completed')->firstOrFail()->user_id);
    }
}
