<?php

namespace App\Providers;

use App\Models\PassportKey;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Passport::authorizationView('mcp.authorize');

        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(60));

        $this->loadPassportKeysFromDatabase();
    }

    /**
     * Passport klíče držíme v databázi (viz PassportKey / GeneratePassportKeys),
     * aby přežily redeploy i smazání volume. Nahrajeme je do configu těsně před
     * tím, než Passport poprvé sestaví OAuth server – tedy jen na MCP/API
     * routách, ne při každém requestu. Když klíče ještě nejsou (čerstvá
     * instalace) nebo DB není dostupná, config zůstane prázdný a `/mcp` vrací
     * 401/500, dokud je admin nevygeneruje v administraci.
     */
    private function loadPassportKeysFromDatabase(): void
    {
        $loader = function (): void {
            static $loaded = false;

            if ($loaded) {
                return;
            }

            $loaded = true;

            try {
                $keys = PassportKey::query()->first();
            } catch (\Throwable) {
                // DB ještě neexistuje (např. během migrací) – klíče necháme být.
                return;
            }

            if ($keys) {
                config([
                    'passport.private_key' => $keys->private_key,
                    'passport.public_key' => $keys->public_key,
                ]);
            }
        };

        $this->app->beforeResolving(AuthorizationServer::class, $loader);
        $this->app->beforeResolving(ResourceServer::class, $loader);
    }
}
