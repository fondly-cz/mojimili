<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Mail\TestEmail;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SmtpSettingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'host' => 'smtp.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'user@example.com',
            'password' => 'tajne',
            'from_address' => 'info@example.com',
            'from_name' => 'Mojimili',
        ], $overrides);
    }

    public function test_admin_can_save_smtp_and_password_is_encrypted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->put(route('smtp-settings.update'), $this->payload())
            ->assertRedirect(route('smtp-settings.edit'));

        $smtp = SmtpSetting::first();
        $this->assertSame('smtp.example.com', $smtp->host);
        $this->assertSame('tajne', $smtp->password);
        $this->assertNotSame('tajne', $smtp->getRawOriginal('password'));
    }

    public function test_blank_password_keeps_the_stored_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin)->put(route('smtp-settings.update'), $this->payload());

        $this->actingAs($admin)->put(route('smtp-settings.update'), $this->payload(['password' => '', 'port' => 587]));

        $this->assertSame('tajne', SmtpSetting::first()->password);
        $this->assertSame(587, SmtpSetting::first()->port);
    }

    public function test_password_is_never_exposed_to_the_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin)->put(route('smtp-settings.update'), $this->payload());

        $this->actingAs($admin)->get(route('smtp-settings.edit'))
            ->assertOk()
            ->assertDontSee('tajne')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Email')
                ->where('smtp.passwordConfigured', true)
                ->missing('smtp.password'));
    }

    public function test_active_smtp_overrides_env_config(): void
    {
        config(['mail.default' => 'log']);

        SmtpSetting::create($this->payload());
        SmtpSetting::active()->applyToConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('tajne', config('mail.mailers.smtp.password'));
        $this->assertSame('info@example.com', config('mail.from.address'));
    }

    public function test_disabled_smtp_is_not_active(): void
    {
        SmtpSetting::create($this->payload(['enabled' => false]));

        $this->assertNull(SmtpSetting::active());
    }

    public function test_test_email_is_sent_directly_and_queued(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->postJson(route('smtp-settings.test'), ['email' => 'prijemce@example.com'])
            ->assertOk()
            ->assertJson(['success' => true]);

        Mail::assertSent(TestEmail::class, fn (TestEmail $m) => $m->hasTo('prijemce@example.com') && ! $m->viaQueue);
        Mail::assertQueued(TestEmail::class, fn (TestEmail $m) => $m->viaQueue);
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);

        $this->actingAs($user)->get(route('smtp-settings.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('smtp-settings.update'), $this->payload())->assertForbidden();
    }

    public function test_delete_removes_custom_smtp(): void
    {
        SmtpSetting::create($this->payload());

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))->delete(route('smtp-settings.destroy'))
            ->assertRedirect();

        $this->assertDatabaseCount('smtp_settings', 0);
    }
}
