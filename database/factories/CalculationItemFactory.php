<?php

namespace Database\Factories;

use App\Models\Calculation;
use App\Models\CalculationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculationItem>
 */
class CalculationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calculation_id' => Calculation::factory(),
            'parent_id' => null,
            'service_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'cost' => 0,
            'margin' => 0,
            'price' => fake()->randomFloat(2, 1000, 50000),
            'days' => fake()->numberBetween(1, 20),
            'payment_period' => 'once',
            'is_accepted' => false,
            'is_required' => false,
            'sort_order' => 0,
        ];
    }

    public function forCalculation(Calculation $calculation): static
    {
        return $this->state(fn () => ['calculation_id' => $calculation->id]);
    }

    public function childOf(CalculationItem $parent): static
    {
        return $this->state(fn () => [
            'calculation_id' => $parent->calculation_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['is_accepted' => true]);
    }
}
