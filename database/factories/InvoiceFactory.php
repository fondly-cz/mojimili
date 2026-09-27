<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => now()->format('Y-m').'-'.fake()->unique()->word(),
            'url' => null,
            'issued_at' => now()->toDateString(),
            'note' => null,
            'user_id' => null,
        ];
    }
}
