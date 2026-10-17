<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\UpdateProjectMembersTool;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTodolistAccessTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    public function test_a_person_added_without_a_choice_sees_no_todolist(): void
    {
        $project = Project::factory()->create();
        $list = Todolist::factory()->forProject($project)->create();
        $todo = Todo::factory()->forTodolist($list)->create();
        $person = $this->manager();

        $this->actingAs($this->admin())->post("/projects/{$project->id}/members", ['user_id' => $person->id])->assertSessionHasNoErrors();

        $this->actingAs($person)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('project.todolists', 0));
        $this->actingAs($person)->get("/todos/{$todo->id}")->assertNotFound();
        $this->actingAs($person)->post("/todolists/{$list->id}/todos", ['name' => 'Nový'])->assertNotFound();
    }

    public function test_a_person_can_see_chosen_todolists_only(): void
    {
        $project = Project::factory()->create();
        $shown = Todolist::factory()->forProject($project)->create();
        $hidden = Todolist::factory()->forProject($project)->create();
        $person = $this->manager();

        $this->actingAs($this->admin())
            ->post("/projects/{$project->id}/members", ['user_id' => $person->id, 'todolist_ids' => [$shown->id]]);

        $this->actingAs($person)->get("/projects/{$project->id}")
            ->assertInertia(fn ($page) => $page
                ->has('project.todolists', 1)
                ->where('project.todolists.0.id', $shown->id));
        $this->actingAs($person)->patch("/todolists/{$hidden->id}", ['name' => 'x'])->assertNotFound();
    }

    public function test_a_person_with_all_todolists_sees_those_added_later(): void
    {
        $project = Project::factory()->create();
        $person = $this->manager();

        $this->actingAs($this->admin())
            ->post("/projects/{$project->id}/members", ['user_id' => $person->id, 'all_todolists' => true]);
        Todolist::factory()->forProject($project)->create();

        $this->actingAs($person)->get("/projects/{$project->id}")
            ->assertInertia(fn ($page) => $page->has('project.todolists', 1));
    }

    public function test_access_can_be_changed_later(): void
    {
        $project = Project::factory()->create();
        $first = Todolist::factory()->forProject($project)->create();
        $second = Todolist::factory()->forProject($project)->create();
        $person = $this->manager();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/projects/{$project->id}/members", ['user_id' => $person->id, 'todolist_ids' => [$first->id]]);
        $this->actingAs($admin)->patch("/projects/{$project->id}/members/{$person->id}", ['todolist_ids' => [$second->id]])
            ->assertSessionHasNoErrors();

        $this->assertFalse($first->isVisibleTo($person));
        $this->assertTrue($second->isVisibleTo($person));
    }

    public function test_work_reports_in_hidden_todolists_are_hidden(): void
    {
        $project = Project::factory()->create();
        $shown = Todolist::factory()->forProject($project)->create();
        $hidden = Todolist::factory()->forProject($project)->create();
        $person = $this->manager();
        $project->grantAccess($person, false, [$shown->id]);
        $visibleReport = WorkReport::factory()->create(['todo_id' => Todo::factory()->forTodolist($shown)->create()->id]);
        $hiddenReport = WorkReport::factory()->create(['todo_id' => Todo::factory()->forTodolist($hidden)->create()->id]);

        $ids = WorkReport::visibleTo($person)->pluck('id');

        $this->assertTrue($ids->contains($visibleReport->id));
        $this->assertFalse($ids->contains($hiddenReport->id));
    }

    public function test_removing_a_person_drops_their_todolist_access(): void
    {
        $project = Project::factory()->create();
        $list = Todolist::factory()->forProject($project)->create();
        $person = $this->manager();
        $project->grantAccess($person, false, [$list->id]);

        $this->actingAs($this->admin())->delete("/projects/{$project->id}/members/{$person->id}");
        $project->grantAccess($person, false);

        $this->assertFalse($list->isVisibleTo($person));
        $this->assertDatabaseMissing('todolist_user', ['user_id' => $person->id]);
    }

    public function test_someone_cannot_hand_out_a_list_they_do_not_see(): void
    {
        $project = Project::factory()->create();
        $mine = Todolist::factory()->forProject($project)->create();
        $secret = Todolist::factory()->forProject($project)->create();
        $actor = $this->manager();
        $project->grantAccess($actor, false, [$mine->id]);
        $person = $this->manager();

        $this->actingAs($actor)->post("/projects/{$project->id}/members", [
            'user_id' => $person->id,
            'todolist_ids' => [$mine->id, $secret->id],
        ]);

        $this->assertTrue($mine->isVisibleTo($person));
        $this->assertFalse($secret->isVisibleTo($person));
    }

    public function test_a_list_created_by_a_limited_member_stays_visible_to_them(): void
    {
        $project = Project::factory()->create();
        $person = $this->manager();
        $project->grantAccess($person, false);

        $this->actingAs($person)->post("/projects/{$project->id}/todolists", ['name' => 'Moje'])->assertSessionHasNoErrors();

        $this->actingAs($person)->get("/projects/{$project->id}")
            ->assertInertia(fn ($page) => $page->has('project.todolists', 1));
    }

    public function test_an_existing_account_is_added_by_e_mail(): void
    {
        $project = Project::factory()->create();
        $person = $this->manager();

        $this->actingAs($this->admin())
            ->post("/projects/{$project->id}/members", ['email' => strtoupper($person->email), 'all_todolists' => true]);

        $this->assertTrue($project->members()->whereKey($person->id)->exists());
        $this->assertDatabaseCount('project_invitations', 0);
    }

    public function test_an_unknown_e_mail_is_invited_and_joins_after_signing_up(): void
    {
        $project = Project::factory()->create();
        $list = Todolist::factory()->forProject($project)->create();

        $this->actingAs($this->admin())
            ->post("/projects/{$project->id}/members", ['email' => 'Nova@Example.com', 'todolist_ids' => [$list->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_invitations', ['project_id' => $project->id, 'email' => 'nova@example.com']);

        $user = User::factory()->create(['email' => 'nova@example.com', 'role' => UserRole::MANAGER]);

        $this->assertTrue($project->isVisibleTo($user));
        $this->assertTrue($list->isVisibleTo($user));
        $this->assertDatabaseCount('project_invitations', 0);
    }

    public function test_an_invitation_can_be_cancelled(): void
    {
        $project = Project::factory()->create();
        $invitation = ProjectInvitation::create(['project_id' => $project->id, 'email' => 'a@example.com']);

        $this->actingAs($this->admin())->delete("/projects/{$project->id}/invitations/{$invitation->id}");

        $this->assertDatabaseCount('project_invitations', 0);
    }

    public function test_known_people_are_those_from_the_viewers_other_projects(): void
    {
        $viewer = $this->manager();
        $colleague = $this->manager();
        $stranger = $this->manager();
        Project::factory()->create()->members()->attach([$viewer->id, $colleague->id]);
        Project::factory()->create()->members()->attach($stranger);
        $project = Project::factory()->create();
        $project->members()->attach($viewer);

        $this->actingAs($viewer)->get("/projects/{$project->id}")
            ->assertInertia(fn ($page) => $page
                ->has('knownPeople', 1)
                ->where('knownPeople.0.id', $colleague->id));
    }

    public function test_the_mcp_tool_hands_out_todolists(): void
    {
        $project = Project::factory()->create();
        $first = Todolist::factory()->forProject($project)->create();
        $second = Todolist::factory()->forProject($project)->create();
        $admin = $this->admin();
        $person = $this->manager();

        CrmServer::actingAs($admin)->tool(UpdateProjectMembersTool::class, [
            'project_id' => $project->id,
            'add_user_ids' => [$person->id],
            'todolist_ids' => [$first->id],
        ])->assertOk();

        $this->assertTrue($first->isVisibleTo($person));
        $this->assertFalse($second->isVisibleTo($person));
    }
}
