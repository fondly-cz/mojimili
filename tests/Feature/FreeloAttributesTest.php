<?php

namespace Tests\Feature;

use App\Enums\TodoPriority;
use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateTodoCommentTool;
use App\Mcp\Tools\CreateTodolistTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Label;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task and project attributes added to match what Freelo keeps (priority, estimate, labels, documents…).
 */
class FreeloAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_a_new_todo_remembers_its_author_and_details(): void
    {
        $user = $this->manager();
        $todolist = Todolist::factory()->create();
        $label = Label::factory()->create();

        $this->actingAs($user)->post("/todolists/{$todolist->id}/todos", [
            'name' => 'Úvodní stránka',
            'priority' => 'high',
            'estimated_minutes' => 90,
            'due_date' => '2026-10-10',
            'due_time' => '14:30',
            'label_ids' => [$label->id],
        ])->assertSessionHasNoErrors();

        $todo = Todo::firstWhere('name', 'Úvodní stránka');
        $this->assertSame($user->id, $todo->created_by_user_id);
        $this->assertSame(TodoPriority::HIGH, $todo->priority);
        $this->assertSame(90, $todo->estimated_minutes);
        $this->assertSame('14:30', $todo->due_time);
        $this->assertSame([$label->id], $todo->labels->pluck('id')->all());
    }

    public function test_completing_a_todo_records_who_did_it(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['is_done' => true])->assertSessionHasNoErrors();
        $this->assertSame($user->id, $todo->refresh()->completed_by_user_id);
        $this->assertNotNull($todo->completed_at);

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['is_done' => false]);
        $this->assertNull($todo->refresh()->completed_by_user_id);
    }

    public function test_labels_on_a_todo_can_be_replaced_and_cleared(): void
    {
        $user = $this->manager();
        [$a, $b] = Label::factory()->count(2)->create();
        $todo = Todo::factory()->create();
        $todo->labels()->attach($a);

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['label_ids' => [$b->id]]);
        $this->assertSame([$b->id], $todo->labels()->pluck('labels.id')->all());

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['label_ids' => []]);
        $this->assertSame(0, $todo->labels()->count());
    }

    public function test_an_invalid_due_time_or_priority_is_rejected(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['due_time' => '25:00', 'priority' => 'urgent'])
            ->assertSessionHasErrors(['due_time', 'priority']);
    }

    public function test_labels_can_be_managed(): void
    {
        $user = $this->manager();

        $this->actingAs($user)->post('/labels', ['name' => 'Faktura', 'color' => '#15acc0'])->assertSessionHasNoErrors();
        $label = Label::firstWhere('name', 'Faktura');
        $this->assertSame('#15acc0', $label->color);

        $this->actingAs($user)->post('/labels', ['name' => 'Faktura'])->assertSessionHasErrors('name');

        $this->actingAs($user)->patch("/labels/{$label->id}", ['name' => 'Pravidelná faktura'])->assertSessionHasNoErrors();
        $this->assertSame('Pravidelná faktura', $label->refresh()->name);
        $this->assertSame('#15acc0', $label->color);

        $this->actingAs($user)->delete("/labels/{$label->id}");
        $this->assertModelMissing($label);
    }

    public function test_a_recurring_todo_copies_its_labels_but_not_its_completion(): void
    {
        $user = $this->manager();
        $label = Label::factory()->create();
        $todo = Todo::factory()->create(['due_date' => today(), 'recurrence_frequency' => 'weekly', 'freelo_id' => 123]);
        $todo->labels()->attach($label);

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['is_done' => true])->assertSessionHasNoErrors();

        $next = Todo::firstWhere('recurrence_previous_id', $todo->id);
        $this->assertSame([$label->id], $next->labels->pluck('id')->all());
        $this->assertNull($next->completed_by_user_id);
        $this->assertNull($next->freelo_id);
    }

    public function test_project_documents_can_be_added_edited_and_removed(): void
    {
        $user = $this->manager();
        $project = Project::factory()->create();

        $this->actingAs($user)->post("/projects/{$project->id}/documents", [
            'name' => 'Přístupy',
            'content' => '<p>Server<script>alert(1)</script></p>',
        ])->assertSessionHasNoErrors();

        $document = $project->documents()->first();
        $this->assertSame($user->id, $document->user_id);
        $this->assertStringNotContainsString('script', $document->content);

        $this->actingAs($user)->patch("/project-documents/{$document->id}", ['name' => 'Hesla'])->assertSessionHasNoErrors();
        $this->assertSame('Hesla', $document->refresh()->name);

        $this->actingAs($user)
            ->get("/projects/{$project->id}")
            ->assertInertia(fn ($page) => $page->where('project.documents.0.name', 'Hesla'));

        $this->actingAs($user)->delete("/project-documents/{$document->id}");
        $this->assertModelMissing($document);
    }

    public function test_a_project_keeps_its_deadline_and_budget(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->manager())->put("/projects/{$project->id}", [
            'name' => $project->name,
            'status' => 'active',
            'due_date' => '2026-12-31',
            'budget' => 150000,
            'budget_minutes' => 6000,
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('2026-12-31', $project->due_date->toDateString());
        $this->assertSame('150000.00', $project->budget);
        $this->assertSame(6000, $project->budget_minutes);
    }

    public function test_the_mcp_tools_handle_the_new_todo_fields(): void
    {
        $user = $this->manager();
        $project = Project::factory()->create();

        CrmServer::actingAs($user)->tool(CreateTodolistTool::class, [
            'project_id' => $project->id,
            'name' => 'Etapa 1',
            'todos' => [[
                'key' => 'a',
                'name' => 'Analýza',
                'priority' => 'low',
                'estimated_minutes' => 120,
                'labels' => ['Web'],
            ]],
        ])->assertOk();

        $todo = Todo::firstWhere('name', 'Analýza');
        $this->assertSame($user->id, $todo->created_by_user_id);
        $this->assertSame(['Web'], $todo->labels->pluck('name')->all());

        CrmServer::actingAs($user)->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'is_done' => true,
            'due_date' => '2026-10-10',
            'due_time' => '09:15',
            'labels' => ['Web', 'Faktura'],
        ])->assertOk();

        $todo->refresh();
        $this->assertSame($user->id, $todo->completed_by_user_id);
        $this->assertSame(['Faktura', 'Web'], $todo->labels->pluck('name')->all());
        $this->assertSame(2, Label::count());

        CrmServer::actingAs($user)->tool(UpdateProjectTool::class, ['id' => $project->id, 'budget' => 50000])->assertOk();

        CrmServer::actingAs($user)->tool(GetProjectTool::class, ['id' => $project->id])
            ->assertOk()
            ->assertSee('"estimated_minutes": 120')
            ->assertSee('"due_time": "09:15"')
            ->assertSee('"budget": "50000.00"');
    }

    public function test_attachment_captions_are_stored_through_mcp(): void
    {
        $todo = Todo::factory()->create();

        CrmServer::actingAs($this->manager())->tool(CreateTodoCommentTool::class, [
            'todo_id' => $todo->id,
            'body' => 'Náhled',
            'attachments' => [['name' => 'a.txt', 'content_base64' => base64_encode('ahoj'), 'caption' => 'Edit produktu']],
        ])->assertOk();

        $this->assertSame('Edit produktu', $todo->comments()->first()->attachments()->first()->caption);
    }
}
