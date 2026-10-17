<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    protected bool $everyoneInEveryProject = false;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_the_feed_shows_only_what_the_viewer_may_see(): void
    {
        $project = Project::factory()->create();
        $shown = Todolist::factory()->forProject($project)->create(['name' => 'Viditelný']);
        $hidden = Todolist::factory()->forProject($project)->create(['name' => 'Skrytý']);
        $viewer = $this->manager();
        $project->grantAccess($viewer, false, [$shown->id]);
        Activity::query()->delete();
        Todo::factory()->forTodolist($shown)->create(['name' => 'Vidím']);
        Todo::factory()->forTodolist($hidden)->create(['name' => 'Nevidím']);

        $this->actingAs($viewer)->getJson("/projects/{$project->id}/activity")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_label', 'Vidím');

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->getJson("/projects/{$project->id}/activity")
            ->assertJsonCount(2, 'data');
    }

    public function test_the_feed_is_missing_for_someone_outside_the_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->manager())->getJson("/projects/{$project->id}/activity")->assertNotFound();
    }
}
