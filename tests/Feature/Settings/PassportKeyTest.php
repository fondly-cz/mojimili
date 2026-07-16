<?php

namespace Tests\Feature\Settings;

use App\Actions\GeneratePassportKeys;
use App\Enums\UserRole;
use App\Models\PassportKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PassportKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_keys(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->post(route('passport-keys.regenerate'))
            ->assertRedirect(route('passport-keys.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('passport_keys', 1);

        $keys = PassportKey::first();
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $keys->private_key);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $keys->public_key);
    }

    public function test_non_admin_cannot_access_or_generate_keys(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $this->actingAs($manager)->get(route('passport-keys.edit'))->assertForbidden();
        $this->actingAs($manager)->post(route('passport-keys.regenerate'))->assertForbidden();

        $this->assertDatabaseCount('passport_keys', 0);
    }

    public function test_regenerating_replaces_keys_in_a_single_row(): void
    {
        $action = app(GeneratePassportKeys::class);

        $first = $action->generate(2048);
        $second = $action->generate(2048);

        $this->assertDatabaseCount('passport_keys', 1);
        $this->assertNotSame($first->private_key, $second->private_key);
    }

    public function test_private_key_is_stored_encrypted(): void
    {
        app(GeneratePassportKeys::class)->generate(2048);

        $raw = DB::table('passport_keys')->value('private_key');

        // Uložený sloupec je zašifrovaný – čistý PEM v něm nesmí být vidět.
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', (string) $raw);
    }
}
