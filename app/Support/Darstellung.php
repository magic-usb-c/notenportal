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

    public const string DIAGRAMM_STANDARD = 'standard';

    public const string DIAGRAMM_FARBENBLIND = 'farbenblind';

    public const array DIAGRAMME = [self::DIAGRAMM_STANDARD, self::DIAGRAMM_FARBENBLIND];

    /** Nachkommastellen für Notendurchschnitte auf interaktiven Seiten (siehe <x-note>); Notenblatt/Exporte/Mails unverändert. */
    public const string NOTENANZEIGE_1 = '1';

    public const string NOTENANZEIGE_2 = '2';

    public const array NOTENANZEIGEN = [self::NOTENANZEIGE_1, self::NOTENANZEIGE_2];

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

    /** Achter Akzent-Wert: eigene Farbe (Feld akzent_eigen, App\Support\Farbe). */
    public const string AKZENT_EIGEN = 'eigen';

    public const string SCHRIFTART_STANDARD = 'standard';

    public const string SCHRIFTART_SERIF = 'serif';

    public const string SCHRIFTART_LESEFREUNDLICH = 'lesefreundlich';

    public const array SCHRIFTARTEN = [self::SCHRIFTART_STANDARD, self::SCHRIFTART_SERIF, self::SCHRIFTART_LESEFREUNDLICH];

    public const string ECKEN_RUND = 'rund';

    public const string ECKEN_ECKIG = 'eckig';

    public const array ECKEN = [self::ECKEN_RUND, self::ECKEN_ECKIG];

    public const string TRANSPARENZ_NORMAL = 'normal';

    public const string TRANSPARENZ_REDUZIERT = 'reduziert';

    public const array TRANSPARENZEN = [self::TRANSPARENZ_NORMAL, self::TRANSPARENZ_REDUZIERT];

    public const string TASTENKUERZEL_AN = 'an';

    public const string TASTENKUERZEL_AUS = 'aus';

    public const array TASTENKUERZEL = [self::TASTENKUERZEL_AN, self::TASTENKUERZEL_AUS];

    /** Hauptnavigation oben (Leiste) oder als Seitenleiste ab 1024 px; darunter immer das Menü. */
    public const string NAVIGATION_OBEN = 'oben';

    public const string NAVIGATION_SEITE = 'seite';

    public const array NAVIGATIONEN = [self::NAVIGATION_OBEN, self::NAVIGATION_SEITE];

    /**
     * Startseite nach dem Login (nur ohne intended-URL, siehe AuthenticatedSessionController) je
     * Dashboard-Rolle (App\Support\DashboardKarten::rolleFuer): Wert => Routenname. «dashboard»
     * ist in jeder Rolle die generische Weiterleitung «/dashboard» (bisheriges Verhalten).
     *
     * @var array<string, array<string, string>>
     */
    public const array STARTSEITEN = [
        DashboardKarten::LERNENDER => [
            'dashboard' => 'dashboard',
            'noten' => 'learner.grades.index',
            'agenda' => 'learner.exams.index',
        ],
        DashboardKarten::BERUFSBILDNER => [
            'dashboard' => 'dashboard',
            'lernende' => 'trainer.learners.index',
            'pruefungstermine' => 'trainer.exams.index',
        ],
        DashboardKarten::ADMIN => [
            'dashboard' => 'dashboard',
            'lernende' => 'admin.learners.index',
            'pruefungstermine' => 'admin.exams.index',
        ],
    ];

    /** Wert => Bezeichnung, über alle Rollen (fürs Profilformular je nach Rolle gefiltert). */
    public const array STARTSEITE_BEZEICHNUNG = [
        'dashboard' => 'Übersicht',
        'noten' => 'Noten',
        'agenda' => 'Agenda',
        'lernende' => 'Lernende',
        'pruefungstermine' => 'Prüfungstermine',
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
     * fallen auf den Standard zurück (theme/akzent/akzent_eigen: null = wie Betrieb/Theme).
     *
     * @return array{theme: ?string, akzent: ?string, akzent_eigen: ?string, schrift: string, schriftart: string, bewegung: string, dichte: string, diagramm: string, notenanzeige: string, ecken: string, transparenz: string, tastenkuerzel: string, navigation: string, startseite: string, karten_ausgeblendet: list<string>}
     */
    public static function fuer(?User $user): array
    {
        $rohdaten = ($user && self::praeferenzenOptionVerfuegbar()) ? (array) ($user->getAttribute('praeferenzen') ?? []) : [];

        $theme = $rohdaten['theme'] ?? null;
        if (! is_string($theme) || ! array_key_exists($theme, Theme::THEMES)) {
            $theme = null;
        }

        $akzentEigen = $rohdaten['akzent_eigen'] ?? null;
        $akzentEigen = Farbe::istGueltigerHex($akzentEigen) ? strtolower($akzentEigen) : null;

        $akzent = $rohdaten['akzent'] ?? null;
        if ($akzent === self::AKZENT_EIGEN) {
            if ($akzentEigen === null) {
                $akzent = null; // «Eigene Farbe» ohne gültigen gespeicherten Wert: stiller Fallback
            }
        } elseif (! is_string($akzent) || ! array_key_exists($akzent, self::AKZENTE)) {
            $akzent = null;
        }
        if ($akzent !== self::AKZENT_EIGEN) {
            $akzentEigen = null; // nur relevant, wenn tatsächlich «Eigene Farbe» gewählt ist
        }

        $schrift = $rohdaten['schrift'] ?? self::SCHRIFT_NORMAL;
        if (! in_array($schrift, self::SCHRIFTGROESSEN, true)) {
            $schrift = self::SCHRIFT_NORMAL;
        }

        $schriftart = $rohdaten['schriftart'] ?? self::SCHRIFTART_STANDARD;
        if (! in_array($schriftart, self::SCHRIFTARTEN, true)) {
            $schriftart = self::SCHRIFTART_STANDARD;
        }

        $bewegung = $rohdaten['bewegung'] ?? self::BEWEGUNG_NORMAL;
        if (! in_array($bewegung, self::BEWEGUNGEN, true)) {
            $bewegung = self::BEWEGUNG_NORMAL;
        }

        $dichte = $rohdaten['dichte'] ?? self::DICHTE_NORMAL;
        if (! in_array($dichte, self::DICHTEN, true)) {
            $dichte = self::DICHTE_NORMAL;
        }

        $diagramm = $rohdaten['diagramm'] ?? self::DIAGRAMM_STANDARD;
        if (! in_array($diagramm, self::DIAGRAMME, true)) {
            $diagramm = self::DIAGRAMM_STANDARD;
        }

        $notenanzeige = $rohdaten['notenanzeige'] ?? self::NOTENANZEIGE_1;
        if (! in_array($notenanzeige, self::NOTENANZEIGEN, true)) {
            $notenanzeige = self::NOTENANZEIGE_1;
        }

        $ecken = $rohdaten['ecken'] ?? self::ECKEN_RUND;
        if (! in_array($ecken, self::ECKEN, true)) {
            $ecken = self::ECKEN_RUND;
        }

        $transparenz = $rohdaten['transparenz'] ?? self::TRANSPARENZ_NORMAL;
        if (! in_array($transparenz, self::TRANSPARENZEN, true)) {
            $transparenz = self::TRANSPARENZ_NORMAL;
        }

        $tastenkuerzel = $rohdaten['tastenkuerzel'] ?? self::TASTENKUERZEL_AN;
        if (! in_array($tastenkuerzel, self::TASTENKUERZEL, true)) {
            $tastenkuerzel = self::TASTENKUERZEL_AN;
        }

        $navigation = $rohdaten['navigation'] ?? self::NAVIGATION_OBEN;
        if (! in_array($navigation, self::NAVIGATIONEN, true)) {
            $navigation = self::NAVIGATION_OBEN;
        }

        // «dashboard» (Standard) ist für jede Rolle gültig – DashboardKarten::rolleFuer (Rollen-
        // Abfragen) wird nur bei einer tatsächlich abweichenden Präferenz gebraucht (AbfragenAnzahlTest:
        // Darstellung::fuer läuft pro Seite mehrfach, z. B. AppServiceProvider, Tastenkürzel-Palette).
        $startseite = $rohdaten['startseite'] ?? 'dashboard';
        if (! is_string($startseite)) {
            $startseite = 'dashboard';
        } elseif ($startseite !== 'dashboard') {
            $rolle = $user ? DashboardKarten::rolleFuer($user) : null;
            $startseitenOptionen = self::STARTSEITEN[$rolle] ?? ['dashboard' => 'dashboard'];
            if (! array_key_exists($startseite, $startseitenOptionen)) {
                $startseite = 'dashboard';
            }
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
            'akzent_eigen' => $akzentEigen,
            'schrift' => $schrift,
            'schriftart' => $schriftart,
            'bewegung' => $bewegung,
            'dichte' => $dichte,
            'diagramm' => $diagramm,
            'notenanzeige' => $notenanzeige,
            'ecken' => $ecken,
            'transparenz' => $transparenz,
            'tastenkuerzel' => $tastenkuerzel,
            'navigation' => $navigation,
            'startseite' => $startseite,
            'karten_ausgeblendet' => $kartenAusgeblendet,
        ];
    }

    /**
     * Routenname fürs Ziel nach dem Login (AuthenticatedSessionController, nur ohne
     * intended-URL): die generische Weiterleitung «dashboard», ausser der Benutzer hat
     * explizit eine andere – für seine Rolle gültige – Startseite gewählt.
     */
    public static function startseiteRoute(?User $user): string
    {
        $rolle = $user ? DashboardKarten::rolleFuer($user) : null;
        $optionen = self::STARTSEITEN[$rolle] ?? ['dashboard' => 'dashboard'];
        $wert = self::fuer($user)['startseite'];

        return $optionen[$wert] ?? 'dashboard';
    }
}
