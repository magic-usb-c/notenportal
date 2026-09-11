<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sitzungs-Timeout (Block AE): die vom Admin gesetzte Laufzeit ersetzt `session.lifetime`.
 * `standard()` merkt sich beim ersten Aufruf den `.env`-Wert, bevor `anwenden()` ihn überschreibt –
 * so bleibt ein Platzhalter im Formular und der Rückfall bei ungültigen Werten immer korrekt.
 */
final class Sitzung
{
    public const int MINUTEN_MIN = 15;

    public const int MINUTEN_MAX = 480;

    private static ?int $standard = null;

    public static function standard(): int
    {
        return self::$standard ??= (int) config('session.lifetime');
    }

    /** Ungültige Werte (nicht numerisch, ausserhalb 15–480) fallen auf standard() zurück – nie 0. */
    public static function minuten(): int
    {
        $standard = self::standard();
        $wert = Einstellungen::get(Einstellungen::SITZUNG_MINUTEN);

        if ($wert === null || $wert === '' || ! is_numeric($wert)) {
            return $standard;
        }

        $minuten = (int) $wert;

        if ($minuten < self::MINUTEN_MIN || $minuten > self::MINUTEN_MAX) {
            return $standard;
        }

        return $minuten;
    }

    public static function anwenden(): void
    {
        config(['session.lifetime' => self::minuten()]);
    }

    /**
     * Text für den 419-Redirect (bootstrap/app.php). Eigene Methode statt eines Literals dort,
     * damit Schluessel::verwendet() (scannt nur app/ und resources/) den Schlüssel findet.
     */
    public static function abgelaufenMeldung(): string
    {
        return __('Die Sitzung ist abgelaufen. Bitte melde dich erneut an.');
    }
}
