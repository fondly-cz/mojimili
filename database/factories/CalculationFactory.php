<?php

namespace Database\Factories;

use App\Models\Calculation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calculation>
 */
class CalculationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_company' => fake()->company(),
            'note' => null,
            'total_price' => 0,
            'total_days' => 0,
            'show_vat' => false,
            'user_id' => null,
            'company_id' => null,
            'company_employee_id' => null,
            'status' => 'draft',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => [
            'company_id' => $company->id,
            'customer_company' => $company->name,
        ]);
    }
}
