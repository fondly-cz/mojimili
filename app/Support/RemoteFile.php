<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads a file from a public https URL for the server, so MCP clients can hand over
 * a link instead of a long base64 string. Every hop is checked against private and
 * reserved addresses and the connection is pinned to the checked IP.
 */
class RemoteFile
{
    private const MAX_REDIRECTS = 5;

    private const TIMEOUT_SECONDS = 30;

    /**
     * @return array{name: ?string, content: string}
     *
     * @throws RuntimeException with a message meant for the MCP client
     */
    public function download(string $url, int $maxBytes): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->request($url, $maxBytes);

            if (! $response->redirect()) {
                break;
            }

            $url = $this->resolveRedirect($url, (string) $response->header('Location'));
        }

        if ($response->redirect()) {
            throw new RuntimeException('Příliš mnoho přesměrování.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Server vrátil HTTP %d.', $response->status()));
        }

        $content = $response->body();

        if ($content === '') {
            throw new RuntimeException('Soubor je prázdný.');
        }

        if (strlen($content) > $maxBytes) {
            throw new RuntimeException(sprintf('Soubor je větší než %d MB.', $maxBytes / 1024 / 1024));
        }

        return ['name' => $this->fileName($response, $url), 'content' => $content];
    }

    private function request(string $url, int $maxBytes): Response
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (($parts['scheme'] ?? null) !== 'https' || ! $host) {
            throw new RuntimeException('Adresa musí začínat na https://.');
        }

        $ip = $this->publicIp($host);
        $port = $parts['port'] ?? 443;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withOptions([
                    'allow_redirects' => false,
                    // Connect to the IP that passed the check, so DNS cannot change in between.
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                ])
                ->get($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Soubor se nepodařilo stáhnout (spojení selhalo).');
        }

        if ((int) $response->header('Content-Length') > $maxBytes) {
            throw new RuntimeException(sprintf('Soubor je větší než %d MB.', $maxBytes / 1024 / 1024));
        }

        return $response;
    }

    private function publicIp(string $host): string
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            throw new RuntimeException(sprintf('Doménu %s se nepodařilo přeložit.', $host));
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Adresa nesmí mířit do interní sítě.');
            }
        }

        return $ips[0];
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }

    private function resolveRedirect(string $from, string $location): string
    {
        if ($location === '') {
            throw new RuntimeException('Přesměrování bez cílové adresy.');
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($from);
        $base = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return str_starts_with($location, '/')
            ? $base.$location
            : $base.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }

    private function fileName(Response $response, string $url): ?string
    {
        $disposition = (string) $response->header('Content-Disposition');

        if (preg_match("/filename\*=(?:UTF-8'')?([^;]+)/i", $disposition, $match)) {
            return basename(rawurldecode(trim($match[1], " \"'")));
        }

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $match)) {
            return basename(trim($match[1]));
        }

        $name = basename(rawurldecode((string) parse_url($url, PHP_URL_PATH)));

        return $name !== '' && str_contains($name, '.') ? $name : null;
    }
}
