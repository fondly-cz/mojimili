<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(3, true),
            'category' => fake()->word(),
            'description' => fake()->sentence(),
            'cost' => fake()->randomFloat(2, 1000, 50000),
            'margin' => 30,
            'days' => fake()->numberBetween(1, 20),
            'payment_period' => 'once',
            'icon' => null,
            'is_active' => true,
            'is_required' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
