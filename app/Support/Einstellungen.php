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

    public const NOTE_GUT = 'note_gut';

    public const NOTE_GENUEGEND = 'note_genuegend';

    public const NOTE_KRITISCH = 'note_kritisch';

    public const RUNDUNG_GESAMT = 'rundung_gesamt';

    public const FRIST_INAKTIV_TAGE = 'frist_inaktiv_tage';

    public const FRIST_LEHRENDE_TAGE = 'frist_lehrende_tage';

    public const THEME = 'theme';

    public const HINWEIS_TEXT = 'hinweis_text';

    public const HINWEIS_ART = 'hinweis_art';

    public const HINWEIS_ZIELGRUPPE = 'hinweis_zielgruppe';

    public const HINWEIS_BEGINN = 'hinweis_beginn';

    public const HINWEIS_ENDE = 'hinweis_ende';

    public const HINWEIS_LOGIN = 'hinweis_login';

    public const HINWEIS_VERSION = 'hinweis_version';

    public const SITZUNG_MINUTEN = 'sitzung_minuten';

    public const LOGO_DATEI = 'logo_datei';

    /** Schwebender Feedback-Knopf während der Testphase (Block G), Vorgabe an: fehlt der Wert, gilt „an“. */
    public const FEEDBACK_KNOPF = 'feedback_knopf';

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
