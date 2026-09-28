<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
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
            'color' => null,
            'sort_order' => 0,
        ];
    }
}
