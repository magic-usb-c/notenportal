<?php

namespace App\Support;

use App\Models\Einstellung;
use Illuminate\Support\Facades\Cache;

/**
 * Betriebsspezifische Einstellungen (Betriebsname, später Grenzwerte, Fristen, Logo).
 * Alle Werte werden gemeinsam gecacht und beim Schreiben invalidiert.
 */
class Einstellungen
{
    public const BETRIEB_NAME = 'betrieb_name';

    private const string CACHE_KEY = 'einstellungen';

    public static function get(string $schluessel, ?string $standard = null): ?string
    {
        return self::alle()[$schluessel] ?? $standard;
    }

    public static function set(string $schluessel, ?string $wert): void
    {
        Einstellung::updateOrCreate(['schluessel' => $schluessel], ['wert' => $wert]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    public static function alle(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Einstellung::pluck('wert', 'schluessel')->all());
    }
}
