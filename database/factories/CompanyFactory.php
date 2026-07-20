<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'ico' => (string) fake()->numberBetween(10000000, 99999999),
            'dic' => null,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => null,
            'postal_code' => fake()->postcode(),
            'country' => 'Česká republika',
            'industry' => fake()->word(),
            'employee_count' => fake()->numberBetween(1, 500),
            'annual_revenue' => fake()->randomFloat(2, 0, 50_000_000),
            'notes' => null,
            'status' => 'active',
        ];
    }

    public function prospect(): static
    {
        return $this->state(fn () => ['status' => 'prospect']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
