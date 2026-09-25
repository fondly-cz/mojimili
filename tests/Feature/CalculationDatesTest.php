<?php

namespace Tests\Feature;

use App\Actions\SaveCalculation;
use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationDatesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Service $service, array $overrides = []): array
    {
        return [
            'customer_name' => 'Test',
            'customer_email' => 't@t.cz',
            'customer_phone' => '1',
            'services' => [
                ['id' => $service->id, 'unique_id' => 'a', 'price' => 100, 'days' => 1, 'payment_period' => 'once'],
            ],
            'created_at' => '2026-01-15',
            'valid_days' => 14,
            ...$overrides,
        ];
    }

    public function test_admin_can_change_created_date_and_validity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $calculation = Calculation::factory()->create(['created_at' => '2026-09-20 10:30:00']);
        $service = Service::factory()->create();

        $this->actingAs($admin)
            ->put("/calculations/{$calculation->id}", $this->payload($service))
            ->assertRedirect();

        $calculation->refresh();
        $this->assertSame('2026-01-15 10:30:00', $calculation->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(14, $calculation->valid_days);
    }

    public function test_new_calculation_uses_the_chosen_date_and_validity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $service = Service::factory()->create();

        $this->actingAs($admin)
            ->post('/calculations', $this->payload($service))
            ->assertRedirect();

        $calculation = Calculation::firstOrFail();
        $this->assertSame('2026-01-15', $calculation->created_at->toDateString());
        $this->assertSame(14, $calculation->valid_days);
    }

    public function test_new_calculation_defaults_to_today_and_thirty_days(): void
    {
        $calculation = app(SaveCalculation::class)->create(
            collect($this->payload(Service::factory()->create()))->except(['created_at', 'valid_days'])->all(),
            null,
        );

        $calculation->refresh();
        $this->assertTrue($calculation->created_at->isToday());
        $this->assertSame(30, $calculation->valid_days);
    }

    public function test_validity_must_be_positive(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $calculation = Calculation::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($admin)
            ->put("/calculations/{$calculation->id}", $this->payload($service, ['valid_days' => 0]))
            ->assertSessionHasErrors('valid_days');
    }

    public function test_update_without_validity_keeps_current_value(): void
    {
        $calculation = Calculation::factory()->create(['valid_days' => 60]);
        $service = Service::factory()->create();

        $payload = $this->payload($service);
        unset($payload['valid_days'], $payload['created_at']);

        app(SaveCalculation::class)->update($calculation, $payload);

        $this->assertSame(60, $calculation->fresh()->valid_days);
    }
}
