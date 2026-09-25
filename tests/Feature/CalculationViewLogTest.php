<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalculationViewLogTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    private const WINDOWS_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    public function test_public_view_is_logged_with_cloudflare_client_ip(): void
    {
        $calculation = Calculation::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders([
                'CF-Connecting-IP' => '89.24.10.20',
                'CF-IPCountry' => 'CZ',
                'X-Forwarded-For' => '89.24.10.20, 162.158.1.1',
                'User-Agent' => self::IPHONE_SAFARI,
            ])
            ->get("/c/{$calculation->access_token}")
            ->assertOk();

        $this->assertDatabaseHas('calculation_views', [
            'calculation_id' => $calculation->id,
            'user_id' => null,
            'ip_address' => '89.24.10.20',
            'country' => 'CZ',
            'device' => 'mobile',
            'platform' => 'iOS 17',
            'browser' => 'Safari 17',
        ]);
    }

    public function test_falls_back_to_first_forwarded_ip_without_cloudflare_header(): void
    {
        $calculation = Calculation::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders(['X-Forwarded-For' => '89.24.10.21, 172.18.0.2'])
            ->get("/c/{$calculation->access_token}");

        $this->assertDatabaseHas('calculation_views', ['ip_address' => '89.24.10.21']);
    }

    public function test_invalid_cloudflare_header_is_ignored(): void
    {
        $calculation = Calculation::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->withHeaders(['CF-Connecting-IP' => 'not-an-ip'])
            ->get("/c/{$calculation->access_token}");

        $this->assertDatabaseHas('calculation_views', ['ip_address' => '10.0.0.9']);
    }

    public function test_every_visit_is_logged_and_shown_newest_first_in_detail_and_edit(): void
    {
        $calculation = Calculation::factory()->create();

        $this->withHeaders(['User-Agent' => self::WINDOWS_CHROME])->get("/c/{$calculation->access_token}");
        $this->withHeaders(['User-Agent' => self::IPHONE_SAFARI])->get("/c/{$calculation->access_token}");

        $user = User::factory()->create(['role' => UserRole::MANAGER]);

        foreach (["/calculations/{$calculation->id}", "/calculations/{$calculation->id}/edit"] as $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('views', 2)
                    ->where('views.0.browser', 'Safari 17')
                    ->where('views.1.browser', 'Chrome 128')
                    ->where('views.1.platform', 'Windows 10/11')
                    ->where('views.1.device', 'desktop')
                );
        }

        // Admin pages themselves are not counted as views.
        $this->assertSame(2, $calculation->views()->count());
    }

    public function test_logged_in_user_opening_public_link_is_marked(): void
    {
        $calculation = Calculation::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/c/{$calculation->access_token}");

        $this->assertDatabaseHas('calculation_views', ['user_id' => $user->id]);
    }

    public function test_inertia_partial_reload_is_not_logged(): void
    {
        $calculation = Calculation::factory()->create();

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Calculations/Show',
            'X-Inertia-Partial-Data' => 'calculation',
        ])->get("/c/{$calculation->access_token}");

        $this->assertSame(0, $calculation->views()->count());
    }

    public function test_user_agent_parser(): void
    {
        $this->assertSame(
            ['device' => 'desktop', 'platform' => 'macOS', 'browser' => 'Firefox 130'],
            UserAgent::parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 14.6; rv:130.0) Gecko/20100101 Firefox/130.0'),
        );
        $this->assertSame(
            ['device' => 'mobile', 'platform' => 'Android 14', 'browser' => 'Samsung Internet 25'],
            UserAgent::parse('Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36'),
        );
        $this->assertSame(
            ['device' => 'desktop', 'platform' => 'Windows 10/11', 'browser' => 'Edge 128'],
            UserAgent::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.2739.42'),
        );
        $this->assertSame('bot', UserAgent::parse('WhatsApp/2.23.20.0')['device']);
        $this->assertSame('unknown', UserAgent::parse(null)['device']);
    }
}
