<?php

namespace Database\Factories;

use App\Models\Calculation;
use App\Models\Project;
use App\Models\Todolist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todolist>
 */
class TodolistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(2, true),
            'description' => null,
            'calculation_id' => null,
            'sort_order' => 0,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }

    public function fromCalculation(Calculation $calculation): static
    {
        return $this->state(fn () => ['calculation_id' => $calculation->id]);
    }
}
