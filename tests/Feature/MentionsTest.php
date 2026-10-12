<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use App\Support\Mentions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionsTest extends TestCase
{
    use RefreshDatabase;

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

    private function mention(User $user): string
    {
        return sprintf('<span class="mention" data-user-id="%d">@%s</span>', $user->id, $user->name);
    }

    public function test_a_project_member_named_in_a_comment_becomes_a_mention(): void
    {
        $project = Project::factory()->create();
        $jana = User::factory()->create(['name' => 'Jana Nováková']);
        $project->members()->attach($jana);
        $todo = $this->todoIn($project);

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/comments", ['body' => '<p>Mrkni na to prosím, @jana nováková, díky.</p>'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            "<p>Mrkni na to prosím, {$this->mention($jana)}, díky.</p>",
            $todo->comments()->firstOrFail()->body,
        );
    }

    public function test_someone_outside_the_project_stays_plain_text(): void
    {
        $project = Project::factory()->create();
        User::factory()->create(['name' => 'Petr Cizí']);
        $todo = $this->todoIn($project);

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/comments", ['body' => '<p>Co na to @Petr Cizí?</p>'])
            ->assertSessionHasNoErrors();

        $body = $todo->comments()->firstOrFail()->body;
        $this->assertStringContainsString('Petr Cizí', $body);
        $this->assertStringNotContainsString('mention', $body);
    }

    public function test_a_mention_forged_in_the_html_is_not_trusted(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create(['name' => 'Petr Cizí']);
        $todo = $this->todoIn($project);

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/comments", ['body' => "<p>{$this->mention($outsider)}</p>"])
            ->assertSessionHasNoErrors();

        $body = $todo->comments()->firstOrFail()->body;
        $this->assertStringContainsString('Petr Cizí', $body);
        $this->assertStringNotContainsString('mention', $body);
        $this->assertStringNotContainsString('data-user-id', $body);
    }

    public function test_saving_a_mention_again_keeps_a_single_span(): void
    {
        $project = Project::factory()->create();
        $jana = User::factory()->create(['name' => 'Jana Nováková']);
        $project->members()->attach($jana);
        $todo = $this->todoIn($project);
        $author = $this->manager();

        $this->actingAs($author)->post("/todos/{$todo->id}/comments", ['body' => '<p>@Jana Nováková</p>']);
        $comment = $todo->comments()->firstOrFail();

        $this->actingAs($author)
            ->patch("/todo-comments/{$comment->id}", ['body' => $comment->body.'<p>Doplnění.</p>'])
            ->assertSessionHasNoErrors();

        $this->assertSame("<p>{$this->mention($jana)}</p><p>Doplnění.</p>", $comment->fresh()->body);
    }

    public function test_the_longest_matching_name_wins_and_links_and_emails_are_left_alone(): void
    {
        $project = Project::factory()->create();
        $jan = User::factory()->create(['name' => 'Jan Novák']);
        $junior = User::factory()->create(['name' => 'Jan Novák ml.']);
        $project->members()->attach([$jan->id, $junior->id]);

        $html = Mentions::link(
            '<p>@Jan Novák ml. a @Jan Novák, ne @Jan Nováková ani info@Jan Novák. <a href="https://example.com">@Jan Novák</a></p>',
            $project,
        );

        $this->assertSame(
            "<p>{$this->mention($junior)} a {$this->mention($jan)}, ne @Jan Nováková ani info@Jan Novák. <a href=\"https://example.com\">@Jan Novák</a></p>",
            $html,
        );
    }

    public function test_a_todo_description_links_mentions_too(): void
    {
        $project = Project::factory()->create();
        $jana = User::factory()->create(['name' => 'Jana Nováková']);
        $project->members()->attach($jana);
        $todo = $this->todoIn($project);

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['description' => '<p>Zadání pro @Jana Nováková</p>'])
            ->assertSessionHasNoErrors();

        $this->assertSame("<p>Zadání pro {$this->mention($jana)}</p>", $todo->fresh()->description);
    }

    public function test_members_are_added_to_and_removed_from_a_project(): void
    {
        $project = Project::factory()->create();
        $jana = User::factory()->create();
        $manager = $this->manager();

        $this->actingAs($manager)->post("/projects/{$project->id}/members", ['user_id' => $jana->id])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post("/projects/{$project->id}/members", ['user_id' => $jana->id])->assertSessionHasNoErrors();
        $this->assertSame([$jana->id], $project->members()->pluck('users.id')->all());

        $this->actingAs($manager)
            ->get("/todos/{$this->todoIn($project)->id}")
            ->assertInertia(fn ($page) => $page->where('todo.todolist.project.members.0.id', $jana->id));

        $this->actingAs($manager)->delete("/projects/{$project->id}/members/{$jana->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, $project->members()->count());
    }

    public function test_whoever_creates_a_project_becomes_its_member(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post('/projects', ['name' => 'Redesign webu'])->assertSessionHasNoErrors();

        $this->assertSame([$manager->id], Project::firstOrFail()->members()->pluck('users.id')->all());
    }
}
