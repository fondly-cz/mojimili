<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyEmployee>
 */
class CompanyEmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->jobTitle(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
