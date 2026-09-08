<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationUnconfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_unconfirm_a_confirmed_calculation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $calculation = Calculation::factory()->confirmed()->create();

        $this->actingAs($admin)
            ->patch("/calculations/{$calculation->id}/unconfirm")
            ->assertRedirect();

        $this->assertSame('draft', $calculation->fresh()->status);
    }

    public function test_manager_cannot_unconfirm_a_calculation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $calculation = Calculation::factory()->confirmed()->create();

        $this->actingAs($manager)
            ->patch("/calculations/{$calculation->id}/unconfirm")
            ->assertForbidden();

        $this->assertSame('confirmed', $calculation->fresh()->status);
    }

    public function test_guests_cannot_unconfirm_a_calculation(): void
    {
        $calculation = Calculation::factory()->confirmed()->create();

        $this->patch("/calculations/{$calculation->id}/unconfirm")
            ->assertRedirect('/login');

        $this->assertSame('confirmed', $calculation->fresh()->status);
    }
}
