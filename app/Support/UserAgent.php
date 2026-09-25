<?php

namespace App\Support;

/**
 * Minimal User-Agent parser: enough to tell device type, OS and browser apart in the view log.
 * The raw string is stored alongside, so nothing is lost when it guesses wrong.
 */
class UserAgent
{
    /**
     * @return array{device: string, platform: ?string, browser: ?string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'device' => self::device($ua),
            'platform' => self::platform($ua),
            'browser' => self::browser($ua),
        ];
    }

    private static function device(string $ua): string
    {
        if ($ua === '') {
            return 'unknown';
        }

        if (preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|curl|wget|python|headless/i', $ua)) {
            return 'bot';
        }

        if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|iPhone|iPod|Android|Windows Phone|Opera Mini/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function platform(string $ua): ?string
    {
        $patterns = [
            '/Windows NT 10/' => 'Windows 10/11',
            '/Windows NT 6\.3/' => 'Windows 8.1',
            '/Windows NT 6\.1/' => 'Windows 7',
            '/Windows/' => 'Windows',
            '/iPhone OS ([\d_]+)/' => 'iOS',
            '/iPad.*OS ([\d_]+)/' => 'iPadOS',
            '/Android ([\d.]+)/' => 'Android',
            '/CrOS/' => 'ChromeOS',
            '/Mac OS X/' => 'macOS',
            '/Linux/' => 'Linux',
        ];

        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $ua, $m)) {
                return isset($m[1]) ? $name.' '.self::major(str_replace('_', '.', $m[1])) : $name;
            }
        }

        return null;
    }

    private static function browser(string $ua): ?string
    {
        // Order matters: most Chromium-based browsers also say "Chrome" and "Safari".
        $patterns = [
            '/Edg(?:e|A|iOS)?\/([\d.]+)/' => 'Edge',
            '/(?:OPR|Opera)\/([\d.]+)/' => 'Opera',
            '/SamsungBrowser\/([\d.]+)/' => 'Samsung Internet',
            '/YaBrowser\/([\d.]+)/' => 'Yandex',
            '/Seznam\.cz\/([\d.]+)|SznProhlizec\/([\d.]+)/' => 'Seznam',
            '/Firefox\/([\d.]+)|FxiOS\/([\d.]+)/' => 'Firefox',
            '/Chrome\/([\d.]+)|CriOS\/([\d.]+)/' => 'Chrome',
            '/Version\/([\d.]+).*Safari/' => 'Safari',
            '/MSIE ([\d.]+)|Trident\/.*rv:([\d.]+)/' => 'Internet Explorer',
        ];

        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $ua, $m)) {
                $version = collect(array_slice($m, 1))->first(fn ($v) => $v !== '');

                return $version ? $name.' '.self::major($version) : $name;
            }
        }

        return null;
    }

    private static function major(string $version): string
    {
        return explode('.', $version)[0];
    }
}
