<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectNote>
 */
class ProjectNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => null,
            'name' => fake()->sentence(3),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
        ];
    }
}
