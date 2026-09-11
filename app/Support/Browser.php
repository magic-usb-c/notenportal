<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kurzer, lesbarer Browser-/Betriebssystem-Text aus dem User-Agent-Header, z. B. «Chrome 128 · Windows».
 * Nur zur Anzeige für Admins – kein Ersatz für echtes UA-Parsing, deckt aber die gängigen Fälle ab.
 */
final class Browser
{
    public static function kurz(?string $userAgent): ?string
    {
        if (blank($userAgent)) {
            return null;
        }

        $browser = self::browserTeil($userAgent);
        $system = self::systemTeil($userAgent);

        $teile = array_filter([$browser, $system]);

        return $teile === [] ? null : implode(' · ', $teile);
    }

    private static function browserTeil(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/Edg\/([\d.]+)/', $ua, $m) => 'Edge '.self::hauptversion($m[1]),
            (bool) preg_match('/OPR\/([\d.]+)/', $ua, $m) => 'Opera '.self::hauptversion($m[1]),
            (bool) preg_match('/CriOS\/([\d.]+)/', $ua, $m) => 'Chrome '.self::hauptversion($m[1]),
            (bool) preg_match('/FxiOS\/([\d.]+)/', $ua, $m) => 'Firefox '.self::hauptversion($m[1]),
            (bool) preg_match('/Chrome\/([\d.]+)/', $ua, $m) && ! str_contains($ua, 'Edg/') => 'Chrome '.self::hauptversion($m[1]),
            (bool) preg_match('/Firefox\/([\d.]+)/', $ua, $m) => 'Firefox '.self::hauptversion($m[1]),
            (bool) preg_match('/Version\/([\d.]+).*Safari/', $ua, $m) => 'Safari '.self::hauptversion($m[1]),
            default => null,
        };
    }

    private static function systemTeil(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') && ! str_contains($ua, 'like Mac OS X') => 'macOS',
            (bool) preg_match('/iPhone|iPad/', $ua) => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };
    }

    private static function hauptversion(string $version): string
    {
        return explode('.', $version)[0];
    }
}
