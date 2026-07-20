<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodolistControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_it_creates_a_todolist_with_todos(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->manager())
            ->post("/projects/{$project->id}/todolists", [
                'name' => 'Etapa 1',
                'todos' => [
                    ['name' => 'Analyza', 'days' => 3],
                    ['name' => 'Vyvoj', 'days' => 10],
                ],
            ])
            ->assertRedirect();

        $todolist = Todolist::firstOrFail();
        $this->assertSame('Etapa 1', $todolist->name);
        $this->assertCount(2, $todolist->todos);
    }

    public function test_it_deletes_a_todolist_and_its_todos(): void
    {
        $todolist = Todolist::factory()->create();
        $todo = Todo::factory()->forTodolist($todolist)->create();

        $this->actingAs($this->manager())
            ->delete("/todolists/{$todolist->id}")
            ->assertRedirect();

        $this->assertNull(Todo::find($todo->id));
    }

    public function test_it_reorders_todos(): void
    {
        $todolist = Todolist::factory()->create();
        $first = Todo::factory()->forTodolist($todolist)->create(['sort_order' => 0]);
        $second = Todo::factory()->forTodolist($todolist)->create(['sort_order' => 1]);

        $this->actingAs($this->manager())
            ->post("/todolists/{$todolist->id}/reorder", ['ids' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(0, $second->refresh()->sort_order);
        $this->assertSame(1, $first->refresh()->sort_order);
    }

    public function test_reordering_cannot_touch_another_lists_todos(): void
    {
        $todolist = Todolist::factory()->create();
        $foreign = Todo::factory()->create(['sort_order' => 7]);

        $this->actingAs($this->manager())
            ->post("/todolists/{$todolist->id}/reorder", ['ids' => [$foreign->id]])
            ->assertRedirect();

        $this->assertSame(7, $foreign->refresh()->sort_order);
    }

    public function test_it_adds_a_todo(): void
    {
        $todolist = Todolist::factory()->create();

        $this->actingAs($this->manager())
            ->post("/todolists/{$todolist->id}/todos", ['name' => 'Novy ukol', 'days' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'todolist_id' => $todolist->id,
            'name' => 'Novy ukol',
            'days' => 2,
        ]);
    }

    public function test_it_rejects_a_parent_from_another_list(): void
    {
        $todolist = Todolist::factory()->create();
        $foreignParent = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->post("/todolists/{$todolist->id}/todos", [
                'name' => 'Osiry ukol',
                'parent_id' => $foreignParent->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(0, $todolist->todos()->count());
    }

    public function test_toggling_a_todo_stamps_and_clears_the_completion_time(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['is_done' => true])
            ->assertRedirect();

        $todo->refresh();
        $this->assertTrue($todo->is_done);
        $this->assertNotNull($todo->completed_at);

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['is_done' => false])
            ->assertRedirect();

        $todo->refresh();
        $this->assertFalse($todo->is_done);
        $this->assertNull($todo->completed_at);
    }

    public function test_it_deletes_a_todo(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->delete("/todos/{$todo->id}")
            ->assertRedirect();

        $this->assertNull(Todo::find($todo->id));
    }

    public function test_it_creates_a_todolist_from_a_calculation(): void
    {
        $project = Project::factory()->create();
        $calculation = Calculation::factory()->confirmed()->create();
        $item = CalculationItem::factory()->forCalculation($calculation)->accepted()->create(['name' => 'Analyza']);

        $this->actingAs($this->manager())
            ->post("/calculations/{$calculation->id}/todolist", [
                'project_id' => $project->id,
                'item_ids' => [$item->id],
            ])
            ->assertRedirect("/projects/{$project->id}");

        $todolist = Todolist::firstOrFail();
        $this->assertSame($calculation->id, $todolist->calculation_id);
        $this->assertSame('Analyza', $todolist->todos->first()->name);
    }

    public function test_it_creates_the_project_inline(): void
    {
        $calculation = Calculation::factory()->confirmed()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $this->actingAs($this->manager())
            ->post("/calculations/{$calculation->id}/todolist", [
                'project_name' => 'Novy projekt z kalkulace',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Novy projekt z kalkulace']);
    }

    public function test_it_requires_a_project(): void
    {
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $this->actingAs($this->manager())
            ->post("/calculations/{$calculation->id}/todolist", [])
            ->assertSessionHasErrors('project_id');
    }

    public function test_it_refuses_a_draft_calculation_with_nothing_accepted(): void
    {
        $project = Project::factory()->create();
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->create();

        $this->actingAs($this->manager())
            ->post("/calculations/{$calculation->id}/todolist", ['project_id' => $project->id])
            ->assertSessionHasErrors('item_ids');

        $this->assertSame(0, Todolist::count());
    }

    public function test_it_refuses_items_from_another_calculation(): void
    {
        $project = Project::factory()->create();
        $calculation = Calculation::factory()->create();
        $foreign = CalculationItem::factory()->create();

        $this->actingAs($this->manager())
            ->post("/calculations/{$calculation->id}/todolist", [
                'project_id' => $project->id,
                'item_ids' => [$foreign->id],
            ])
            ->assertSessionHasErrors('item_ids');

        $this->assertSame(0, Todolist::count());
    }
}
