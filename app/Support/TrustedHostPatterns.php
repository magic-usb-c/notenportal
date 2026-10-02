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

        $patterns = array_map(fn (string $host) => '^'.preg_quote($host).'$', $hosts);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $patterns[] = '^(.+\.)?'.preg_quote($appHost).'$';
        }

        return $patterns;
    }
}
