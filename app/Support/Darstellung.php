<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Darstellungs-Präferenzen (benutzer.praeferenzen, JSON). Erweiterbar ohne
 * neue Migration: unbekannte Schlüssel werden beim Lesen ignoriert, ungültige gespeicherte
 * Werte fallen still auf den Standard zurück. Theme-Wahl: siehe App\Support\Theme::fuer().
 */
final class Darstellung
{
    public const string SCHRIFT_NORMAL = 'normal';

    public const string SCHRIFT_GROSS = 'gross';

    public const string SCHRIFT_SEHR_GROSS = 'sehr-gross';

    public const array SCHRIFTGROESSEN = [self::SCHRIFT_NORMAL, self::SCHRIFT_GROSS, self::SCHRIFT_SEHR_GROSS];

    public const string BEWEGUNG_NORMAL = 'normal';

    public const string BEWEGUNG_REDUZIERT = 'reduziert';

    public const array BEWEGUNGEN = [self::BEWEGUNG_NORMAL, self::BEWEGUNG_REDUZIERT];

    public const string DICHTE_NORMAL = 'normal';

    public const string DICHTE_KOMPAKT = 'kompakt';

    public const array DICHTEN = [self::DICHTE_NORMAL, self::DICHTE_KOMPAKT];

    /** Wert => Bezeichnung */
    public const array AKZENTE = [
        'blau' => 'Blau',
        'petrol' => 'Petrol',
        'gruen' => 'Grün',
        'violett' => 'Violett',
        'magenta' => 'Magenta',
        'orange' => 'Orange',
        'rot' => 'Rot',
    ];

    private static ?bool $spalteVorhanden = null;

    /**
     * Die Spalte gibt es erst nach der Migration 2026_09_11_000009. Ist die DB nicht erreichbar,
     * gilt «nicht verfügbar» (ohne zu cachen) – sonst bricht auch die Fehlerseite.
     */
    public static function praeferenzenOptionVerfuegbar(): bool
    {
        if (self::$spalteVorhanden === null) {
            try {
                self::$spalteVorhanden = Schema::hasColumn('benutzer', 'praeferenzen');
            } catch (\Throwable) {
                return false;
            }
        }

        return self::$spalteVorhanden;
    }

    /**
     * Gültige, aufbereitete Präferenzen für die Seite. Ungültige oder fehlende Werte
     * fallen auf den Standard zurück (theme/akzent: null = wie Betrieb/Theme).
     *
     * @return array{theme: ?string, akzent: ?string, schrift: string, bewegung: string, dichte: string, karten_ausgeblendet: list<string>}
     */
    public static function fuer(?User $user): array
    {
        $rohdaten = ($user && self::praeferenzenOptionVerfuegbar()) ? (array) ($user->getAttribute('praeferenzen') ?? []) : [];

        $theme = $rohdaten['theme'] ?? null;
        if (! is_string($theme) || ! array_key_exists($theme, Theme::THEMES)) {
            $theme = null;
        }

        $akzent = $rohdaten['akzent'] ?? null;
        if (! is_string($akzent) || ! array_key_exists($akzent, self::AKZENTE)) {
            $akzent = null;
        }

        $schrift = $rohdaten['schrift'] ?? self::SCHRIFT_NORMAL;
        if (! in_array($schrift, self::SCHRIFTGROESSEN, true)) {
            $schrift = self::SCHRIFT_NORMAL;
        }

        $bewegung = $rohdaten['bewegung'] ?? self::BEWEGUNG_NORMAL;
        if (! in_array($bewegung, self::BEWEGUNGEN, true)) {
            $bewegung = self::BEWEGUNG_NORMAL;
        }

        $dichte = $rohdaten['dichte'] ?? self::DICHTE_NORMAL;
        if (! in_array($dichte, self::DICHTEN, true)) {
            $dichte = self::DICHTE_NORMAL;
        }

        $kartenAusgeblendet = $rohdaten['karten_ausgeblendet'] ?? [];
        if (! is_array($kartenAusgeblendet)) {
            $kartenAusgeblendet = [];
        }
        $gueltigeKarten = DashboardKarten::alleSchluessel();
        $kartenAusgeblendet = array_values(array_unique(array_filter(
            $kartenAusgeblendet,
            fn ($wert) => is_string($wert) && in_array($wert, $gueltigeKarten, true)
        )));

        return [
            'theme' => $theme,
            'akzent' => $akzent,
            'schrift' => $schrift,
            'bewegung' => $bewegung,
            'dichte' => $dichte,
            'karten_ausgeblendet' => $kartenAusgeblendet,
        ];
    }
}
