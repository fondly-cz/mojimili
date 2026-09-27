<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Todo;
use App\Models\WorkReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkReport>
 */
class WorkReportFactory extends Factory
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
            'date' => now()->toDateString(),
            'minutes' => fake()->randomElement([15, 30, 60, 90, 120]),
            'hourly_rate' => 1000,
            'description' => null,
            'invoice_id' => null,
        ];
    }

    public function forTodo(Todo $todo): static
    {
        return $this->state(fn () => ['todo_id' => $todo->id]);
    }

    public function invoicedIn(Invoice $invoice): static
    {
        return $this->state(fn () => ['invoice_id' => $invoice->id]);
    }
}
