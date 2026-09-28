<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateTodoTool;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtasksTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_the_detail_page_lists_subtasks_and_move_targets(): void
    {
        $todo = Todo::factory()->create();
        Todo::factory()->childOf($todo)->create(['name' => 'Hero sekce']);

        $this->actingAs($this->manager())
            ->get("/todos/{$todo->id}")
            ->assertInertia(fn ($page) => $page
                ->where('todo.children.0.name', 'Hero sekce')
                ->has('todo.children.0.children_count')
                ->has('listTodos', 2)
            );
    }

    public function test_a_todo_can_be_moved_under_another_and_back_to_the_top(): void
    {
        $user = $this->manager();
        $parent = Todo::factory()->create();
        Todo::factory()->childOf($parent)->create(['sort_order' => 5]);
        $todo = Todo::factory()->create(['todolist_id' => $parent->todolist_id]);

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['parent_id' => $parent->id])->assertSessionHasNoErrors();
        $todo->refresh();
        $this->assertSame($parent->id, $todo->parent_id);
        $this->assertSame(6, $todo->sort_order);

        $this->actingAs($user)->patch("/todos/{$todo->id}", ['parent_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($todo->refresh()->parent_id);
    }

    public function test_a_todo_cannot_be_moved_into_itself_its_subtask_or_another_list(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();
        $child = Todo::factory()->childOf($todo)->create();
        $foreign = Todo::factory()->create();

        foreach ([$todo->id, $child->id, $foreign->id] as $parentId) {
            $this->actingAs($user)
                ->patch("/todos/{$todo->id}", ['parent_id' => $parentId])
                ->assertSessionHasErrors('parent_id');
        }

        $this->assertNull($todo->refresh()->parent_id);
    }

    public function test_a_recurring_todo_cannot_be_moved_under_a_recurring_parent(): void
    {
        $parent = Todo::factory()->create(['recurrence_frequency' => 'weekly', 'due_date' => today()]);
        $todo = Todo::factory()->create(['todolist_id' => $parent->todolist_id, 'recurrence_frequency' => 'daily', 'due_date' => today()]);

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['parent_id' => $parent->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_subtasks_can_be_reordered(): void
    {
        $parent = Todo::factory()->create();
        $a = Todo::factory()->childOf($parent)->create(['sort_order' => 0]);
        $b = Todo::factory()->childOf($parent)->create(['sort_order' => 1]);

        $this->actingAs($this->manager())
            ->post("/todolists/{$parent->todolist_id}/reorder", ['ids' => [$b->id, $a->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$b->id, $a->id], $parent->children()->pluck('id')->all());
    }

    public function test_deleting_a_subtask_from_the_parent_detail_stays_there(): void
    {
        $parent = Todo::factory()->create();
        $child = Todo::factory()->childOf($parent)->create();

        $this->actingAs($this->manager())
            ->from(route('todos.show', $parent))
            ->delete("/todos/{$child->id}")
            ->assertRedirect(route('todos.show', $parent));

        $this->assertModelMissing($child);
    }

    public function test_deleting_a_todo_from_its_own_detail_returns_to_the_project(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->from(route('todos.show', $todo))
            ->delete("/todos/{$todo->id}")
            ->assertRedirect(route('projects.show', $todo->todolist->project_id));
    }

    public function test_mcp_adds_a_subtask_and_moves_todos(): void
    {
        $user = $this->manager();
        $parent = Todo::factory()->create();
        Todo::factory()->childOf($parent)->create(['sort_order' => 3]);

        CrmServer::actingAs($user)->tool(CreateTodoTool::class, [
            'parent_id' => $parent->id,
            'name' => 'Kontrola',
            'estimated_minutes' => 30,
            'labels' => ['Web'],
        ])->assertOk()->assertSee('podúkol úkolu');

        $subtask = Todo::firstWhere('name', 'Kontrola');
        $this->assertSame($parent->todolist_id, $subtask->todolist_id);
        $this->assertSame($parent->id, $subtask->parent_id);
        $this->assertSame(4, $subtask->sort_order);
        $this->assertSame($user->id, $subtask->created_by_user_id);
        $this->assertSame(['Web'], $subtask->labels->pluck('name')->all());

        CrmServer::actingAs($user)->tool(CreateTodoTool::class, [
            'todolist_id' => $parent->todolist_id,
            'name' => 'Samostatný',
        ])->assertOk();
        $this->assertNull(Todo::firstWhere('name', 'Samostatný')->parent_id);

        CrmServer::actingAs($user)->tool(UpdateTodoTool::class, ['id' => $subtask->id, 'parent_id' => null])->assertOk();
        $this->assertNull($subtask->refresh()->parent_id);

        CrmServer::actingAs($user)->tool(UpdateTodoTool::class, ['id' => $parent->id, 'parent_id' => $parent->id])
            ->assertHasErrors(['Úkol nelze vložit sám do sebe.']);
    }

    public function test_mcp_needs_a_list_or_a_parent(): void
    {
        CrmServer::actingAs($this->manager())->tool(CreateTodoTool::class, ['name' => 'Bez seznamu'])
            ->assertHasErrors();
    }
}
