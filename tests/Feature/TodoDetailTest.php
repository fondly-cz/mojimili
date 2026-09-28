<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoDetailTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_the_detail_page_shows_the_todo_with_its_context(): void
    {
        $parent = Todo::factory()->create(['name' => 'Web']);
        $todo = Todo::factory()->create(['todolist_id' => $parent->todolist_id, 'parent_id' => $parent->id, 'name' => 'Úvodní stránka']);
        Todo::factory()->create(['todolist_id' => $parent->todolist_id, 'parent_id' => $todo->id, 'name' => 'Hero sekce']);

        $this->actingAs($this->manager())
            ->get("/todos/{$todo->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Todos/Show')
                ->where('todo.name', 'Úvodní stránka')
                ->where('todo.parent.name', 'Web')
                ->where('todo.children.0.name', 'Hero sekce')
                ->has('users')
            );
    }

    public function test_deleting_a_todo_returns_to_its_project(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->delete("/todos/{$todo->id}")
            ->assertRedirect(route('projects.show', $todo->todolist->project_id));

        $this->assertModelMissing($todo);
    }

    public function test_the_description_is_stored_as_sanitized_html(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['description' => '<p>Zadání<img src="x" onerror="alert(1)"><script>alert(2)</script></p>'])
            ->assertSessionHasNoErrors();

        $description = $todo->refresh()->description;
        $this->assertStringStartsWith('<p>Zadání', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('script', $description);
    }

    public function test_plain_text_and_empty_descriptions_are_normalised(): void
    {
        $this->assertStringStartsWith('<p>Řádek', Todo::factory()->create(['description' => "Řádek\ndruhý"])->description);
        $this->assertNull(Todo::factory()->create(['description' => '<p><br></p>'])->description);
    }
}
