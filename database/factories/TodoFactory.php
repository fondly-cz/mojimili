<?php

namespace Database\Factories;

use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'todolist_id' => Todolist::factory(),
            'parent_id' => null,
            'calculation_item_id' => null,
            'name' => fake()->sentence(3),
            'description' => null,
            'days' => fake()->numberBetween(0, 10),
            'is_done' => false,
            'completed_at' => null,
            'assigned_user_id' => null,
            'due_date' => null,
            'sort_order' => 0,
        ];
    }

    public function forTodolist(Todolist $todolist): static
    {
        return $this->state(fn () => ['todolist_id' => $todolist->id]);
    }

    public function childOf(Todo $parent): static
    {
        return $this->state(fn () => [
            'todolist_id' => $parent->todolist_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'is_done' => true,
            'completed_at' => now(),
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_user_id' => $user->id]);
    }
}
