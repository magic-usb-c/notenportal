<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vertraute Host-Header (bootstrap/app.php → TrustHosts): die Namen und Adressen aus TRUSTED_HOSTS plus
 * der Host aus APP_URL samt Subdomains. Ist TRUSTED_HOSTS leer oder fehlt der Schlüssel (etwa nach
 * `git pull` ohne Installer), gibt es keine Einschränkung – `notenportal:bereitschaft` warnt dann.
 */
final class TrustedHostPatterns
{
    /** @return list<string> Namen und Adressen aus TRUSTED_HOSTS, bereinigt. */
    public static function hosts(): array
    {
        $namen = array_map('trim', explode(',', (string) config('app.trusted_hosts')));

        return array_values(array_filter($namen, fn (string $name) => $name !== ''));
    }

    /** @return list<string> Regex-Muster für TrustHosts; leer = keine Einschränkung. */
    public static function patterns(): array
    {
        $hosts = self::hosts();
        if ($hosts === []) {
            return [];
        }

        // Symfony liefert IPv6-Hosts in eckigen Klammern («[::1]»), darum so auch das Muster
        $patterns = array_map(fn (string $host) => '^'.preg_quote(self::ipv6Klammern($host)).'$', $hosts);
        $appUrl = (string) config('app.url');
        $appHost = parse_url(str_contains($appUrl, '://') ? $appUrl : '//'.$appUrl, PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $patterns[] = '^(.+\.)?'.preg_quote($appHost).'$';
        }

        return $patterns;
    }

    private static function ipv6Klammern(string $host): string
    {
        return str_contains($host, ':') && ! str_starts_with($host, '[') ? '['.$host.']' : $host;
    }
}
