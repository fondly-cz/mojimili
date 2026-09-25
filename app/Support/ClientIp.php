<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the visitor's real IP. The app runs behind Cloudflare and Traefik, so
 * $request->ip() alone would return a Cloudflare edge or a Docker network address.
 */
class ClientIp
{
    public static function resolve(Request $request): ?string
    {
        $candidates = [
            $request->header('CF-Connecting-IP'),
            $request->header('True-Client-IP'),
            // Left-most entry is the original client; later ones are proxies.
            trim(explode(',', (string) $request->header('X-Forwarded-For'))[0]),
            $request->header('X-Real-IP'),
        ];

        foreach ($candidates as $ip) {
            if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }

        return $request->ip();
    }
}
