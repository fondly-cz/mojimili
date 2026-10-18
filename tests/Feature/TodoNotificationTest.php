<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\TodoActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TodoNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function todoWithMember(User $member): Todo
    {
        $todo = Todo::factory()->create();
        $todo->todolist->project->members()->syncWithoutDetaching([$member->id]);

        return $todo;
    }

    public function test_assigning_a_todo_notifies_the_assignee(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $worker = User::factory()->create(['role' => UserRole::COLLABORATOR]);
        $todo = $this->todoWithMember($worker);

        $this->actingAs($manager)->patch("/todos/{$todo->id}", ['assigned_user_id' => $worker->id]);

        Notification::assertSentTo($worker, TodoActivity::class, fn ($n) => $n->type === TodoActivity::ASSIGNED);
    }

    public function test_assigning_to_yourself_notifies_nobody(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $todo = $this->todoWithMember($manager);

        $this->actingAs($manager)->patch("/todos/{$todo->id}", ['assigned_user_id' => $manager->id]);

        Notification::assertNothingSent();
    }

    public function test_watchers_hear_about_comments(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $worker = User::factory()->create(['role' => UserRole::COLLABORATOR]);
        $todo = $this->todoWithMember($worker);

        $this->actingAs($manager)->patch("/todos/{$todo->id}", ['watcher_ids' => [$worker->id]]);
        $this->actingAs($manager)->post("/todos/{$todo->id}/comments", ['body' => '<p>Ahoj</p>']);

        Notification::assertSentTo($worker, TodoActivity::class, fn ($n) => $n->type === TodoActivity::COMMENTED);
    }

    public function test_notifications_can_be_listed_and_read(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $worker = User::factory()->create(['role' => UserRole::COLLABORATOR]);
        $todo = $this->todoWithMember($worker);
        $worker->notify(new TodoActivity($todo, $manager, TodoActivity::ASSIGNED));

        $this->actingAs($worker)->getJson('/notifications')->assertOk()->assertJsonPath('unread', 1);
        $this->actingAs($worker)->postJson('/notifications/read-all')->assertOk();
        $this->actingAs($worker)->getJson('/notifications')->assertJsonPath('unread', 0);
    }
}
