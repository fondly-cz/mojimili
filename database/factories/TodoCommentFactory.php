<?php

namespace Database\Factories;

use App\Models\Todo;
use App\Models\TodoComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TodoComment>
 */
class TodoCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'todo_id' => Todo::factory(),
            'user_id' => null,
            'body' => '<p>'.fake()->sentence().'</p>',
        ];
    }
}
