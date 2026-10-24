<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use App\Notifications\TodoActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $everyoneInEveryProject = false;

    private function todoIn(Project $project, ?Todolist $list = null): Todo
    {
        $list ??= Todolist::factory()->create(['project_id' => $project->id]);

        return Todo::factory()->create(['todolist_id' => $list->id]);
    }

    public function test_project_follower_hears_about_comments_without_being_assignee_or_watcher(): void
    {
        Notification::fake();
        $author = User::factory()->create(['role' => UserRole::MANAGER]);
        $follower = User::factory()->create(['role' => UserRole::MANAGER]);
        $project = Project::factory()->create();
        $project->grantAccess($author, true);
        $project->grantAccess($follower, true);
        $project->setFollowedBy($follower, true);
        $todo = $this->todoIn($project);

        $this->actingAs($author)->post("/todos/{$todo->id}/comments", ['body' => '<p>Ahoj</p>']);

        Notification::assertSentTo($follower, TodoActivity::class, fn ($n) => $n->type === TodoActivity::COMMENTED);
        Notification::assertNotSentTo($author, TodoActivity::class);
    }

    public function test_assignee_becomes_a_watcher_and_stays_one_after_handing_over(): void
    {
        Notification::fake();
        $author = User::factory()->create(['role' => UserRole::MANAGER]);
        $first = User::factory()->create(['role' => UserRole::COLLABORATOR]);
        $second = User::factory()->create(['role' => UserRole::COLLABORATOR]);
        $project = Project::factory()->create();
        foreach ([$author, $first, $second] as $member) {
            $project->grantAccess($member, true);
        }
        $todo = $this->todoIn($project);

        $this->actingAs($author)->patch("/todos/{$todo->id}", ['assigned_user_id' => $first->id, 'watcher_ids' => []]);
        $this->actingAs($author)->patch("/todos/{$todo->id}", ['assigned_user_id' => $second->id, 'watcher_ids' => [$first->id]]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $todo->watchers()->pluck('users.id')->all());

        $this->actingAs($author)->post("/todos/{$todo->id}/comments", ['body' => '<p>Ahoj</p>']);

        Notification::assertSentTo($first, TodoActivity::class, fn ($n) => $n->type === TodoActivity::COMMENTED);
        Notification::assertSentTo($second, TodoActivity::class, fn ($n) => $n->type === TodoActivity::COMMENTED);
    }

    public function test_follower_is_not_told_about_lists_they_cannot_see(): void
    {
        Notification::fake();
        $author = User::factory()->create(['role' => UserRole::MANAGER]);
        $follower = User::factory()->create(['role' => UserRole::COLLABORATOR]);
        $project = Project::factory()->create();
        $project->grantAccess($author, true);
        $project->grantAccess($follower, false);
        $project->setFollowedBy($follower, true);
        $todo = $this->todoIn($project);

        $this->actingAs($author)->post("/todos/{$todo->id}/comments", ['body' => '<p>Ahoj</p>']);

        Notification::assertNotSentTo($follower, TodoActivity::class);
    }

    public function test_person_who_is_watcher_and_follower_is_told_once(): void
    {
        Notification::fake();
        $author = User::factory()->create(['role' => UserRole::MANAGER]);
        $both = User::factory()->create(['role' => UserRole::MANAGER]);
        $project = Project::factory()->create();
        $project->grantAccess($author, true);
        $project->grantAccess($both, true);
        $project->setFollowedBy($both, true);
        $todo = $this->todoIn($project);
        $todo->watchers()->attach($both->id);

        $this->actingAs($author)->post("/todos/{$todo->id}/comments", ['body' => '<p>Ahoj</p>']);

        Notification::assertSentToTimes($both, TodoActivity::class, 1);
    }

    public function test_admin_can_follow_a_project_they_are_not_member_of(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $project = Project::factory()->create();

        $this->actingAs($admin)->patch(route('projects.follow', $project), ['follow' => true])->assertRedirect();

        $this->assertTrue($project->followers()->whereKey($admin->id)->exists());

        $this->actingAs($admin)->patch(route('projects.follow', $project), ['follow' => false]);

        $this->assertFalse($project->followers()->whereKey($admin->id)->exists());
    }

    public function test_outsider_cannot_follow_a_project(): void
    {
        $outsider = User::factory()->create(['role' => UserRole::MANAGER]);
        $project = Project::factory()->create();

        $this->actingAs($outsider)->patch(route('projects.follow', $project), ['follow' => true])->assertNotFound();
    }

    public function test_channels_follow_the_user_preferences(): void
    {
        $user = User::factory()->create(['notify_in_app' => true, 'notify_by_mail' => false]);
        $notification = new TodoActivity(Todo::factory()->create(), User::factory()->create(), TodoActivity::COMMENTED);

        $this->assertSame(['database'], $notification->via($user));

        $user->update(['notify_in_app' => false, 'notify_by_mail' => true]);
        $this->assertSame(['mail'], $notification->via($user->fresh()));
    }

    public function test_user_can_save_channel_preferences(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);

        $this->actingAs($user)->patch(route('profile.notifications.update'), [
            'notify_in_app' => false,
            'notify_by_mail' => true,
        ])->assertRedirect();

        $this->assertFalse($user->fresh()->notify_in_app);
        $this->assertTrue($user->fresh()->notify_by_mail);
    }
}
