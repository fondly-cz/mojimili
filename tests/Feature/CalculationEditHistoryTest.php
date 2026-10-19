<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationEditHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Calculation $calculation, array $services): array
    {
        return [
            'customer_name' => $calculation->customer_name,
            'customer_email' => $calculation->customer_email,
            'customer_phone' => $calculation->customer_phone,
            'services' => $services,
            'created_at' => now()->toDateString(),
            'valid_days' => 30,
        ];
    }

    private function row(CalculationItem $item, array $override = []): array
    {
        return [
            'id' => $item->service_id,
            'unique_id' => (string) $item->id,
            'parent_id' => $item->parent_id ? (string) $item->parent_id : null,
            'price' => (float) $item->price,
            'days' => $item->days,
            'payment_period' => $item->payment_period->value,
            ...$override,
        ];
    }

    public function test_adding_an_item_keeps_the_accepted_ones_and_the_item_ids(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $calculation = Calculation::factory()->confirmed()->create();
        $accepted = CalculationItem::factory()->create(['service_id' => Service::factory()->create()->id, 'calculation_id' => $calculation->id, 'is_accepted' => true, 'price' => 1000, 'days' => 2]);
        $declined = CalculationItem::factory()->create(['service_id' => Service::factory()->create()->id, 'calculation_id' => $calculation->id, 'is_accepted' => false]);
        $service = Service::factory()->create();

        $this->actingAs($admin)
            ->patch("/calculations/{$calculation->id}", $this->payload($calculation, [
                $this->row($accepted),
                $this->row($declined),
                ['id' => $service->id, 'unique_id' => 'new1', 'parent_id' => null, 'price' => 500, 'days' => 1, 'payment_period' => 'once'],
            ]))
            ->assertRedirect();

        $calculation->refresh();
        $this->assertSame('confirmed', $calculation->status);
        $this->assertCount(3, $calculation->items);
        $this->assertTrue($accepted->fresh()->is_accepted);
        $this->assertFalse($declined->fresh()->is_accepted);
        $this->assertFalse($calculation->items->last()->is_accepted);
        $this->assertEquals(1000, $calculation->total_price);
        $this->assertSame(2, (int) $calculation->total_days);
    }

    public function test_item_changes_are_written_to_the_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $calculation = Calculation::factory()->create();
        $changed = CalculationItem::factory()->create(['service_id' => Service::factory()->create()->id, 'calculation_id' => $calculation->id, 'price' => 1000]);
        $removed = CalculationItem::factory()->create(['service_id' => Service::factory()->create()->id, 'calculation_id' => $calculation->id, 'name' => 'Pryč']);
        $service = Service::factory()->create(['name' => 'Nová služba']);

        $this->actingAs($admin)
            ->patch("/calculations/{$calculation->id}", $this->payload($calculation, [
                $this->row($changed, ['price' => 1500]),
                ['id' => $service->id, 'unique_id' => 'new1', 'parent_id' => null, 'price' => 500, 'days' => 1, 'payment_period' => 'once'],
            ]))
            ->assertRedirect();

        $events = Activity::where('subject_type', 'calculation')->where('subject_id', $calculation->id)->pluck('event');
        $this->assertContains('calculation.item_changed', $events);
        $this->assertContains('calculation.item_added', $events);
        $this->assertContains('calculation.item_removed', $events);

        $texts = Activity::where('subject_id', $calculation->id)->get()->pluck('text')->implode("\n");
        $this->assertStringContainsString('Odebrána položka „Pryč“', $texts);
        $this->assertStringContainsString('cena: 1000.00 → 1500', $texts);

        $this->actingAs($admin)
            ->get("/calculations/{$calculation->id}/edit")
            ->assertInertia(fn ($page) => $page->has('history'));
    }

    public function test_unconfirming_is_logged(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $calculation = Calculation::factory()->confirmed()->create();

        $this->actingAs($admin)->patch("/calculations/{$calculation->id}/unconfirm");

        $this->assertDatabaseHas('activities', ['event' => 'calculation.unconfirmed', 'subject_id' => $calculation->id, 'user_id' => $admin->id]);
        $this->assertDatabaseMissing('activities', ['event' => 'calculation.updated', 'subject_id' => $calculation->id]);
    }
}
