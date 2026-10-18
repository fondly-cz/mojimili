<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectNotesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $everyoneInEveryProject = false;

    public function test_project_notes_can_be_added_edited_and_removed(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);
        $project = Project::factory()->create();
        $project->members()->attach($user, ['all_todolists' => true]);

        $this->actingAs($user)->post("/projects/{$project->id}/notes", [
            'name' => 'Schůzka',
            'content' => '<p>Zápis<script>alert(1)</script></p>',
        ])->assertSessionHasNoErrors();

        $note = $project->notes()->first();
        $this->assertSame($user->id, $note->user_id);
        $this->assertStringNotContainsString('script', $note->content);

        $this->actingAs($user)->patch("/project-notes/{$note->id}", ['name' => 'Porada'])->assertSessionHasNoErrors();
        $this->assertSame('Porada', $note->refresh()->name);

        $this->actingAs($user)
            ->get("/projects/{$project->id}")
            ->assertInertia(fn ($page) => $page->where('project.notes.0.name', 'Porada'));

        $this->actingAs($user)->delete("/project-notes/{$note->id}");
        $this->assertModelMissing($note);
    }

    public function test_notes_are_missing_for_someone_outside_the_project(): void
    {
        $outsider = User::factory()->create(['role' => UserRole::MANAGER]);
        $note = ProjectNote::factory()->create();

        $this->actingAs($outsider)->patch("/project-notes/{$note->id}", ['name' => 'Přepsáno'])->assertNotFound();
        $this->actingAs($outsider)->delete("/project-notes/{$note->id}")->assertNotFound();
    }
}
