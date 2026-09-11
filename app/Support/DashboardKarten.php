<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Karten der drei Dashboards (resources/views/dashboards/*.blade.php), ein-/ausblendbar über
 * die persönliche Präferenz benutzer.praeferenzen.karten_ausgeblendet (App\Support\Darstellung).
 * Schlüssel sind stabil und dürfen nicht umbenannt werden (sonst verliert die gespeicherte
 * Präferenz ihre Wirkung – der unbekannte Schlüssel wird beim Lesen einfach ignoriert).
 */
final class DashboardKarten
{
    public const string ADMIN = 'admin';

    public const string BERUFSBILDNER = 'berufsbildner';

    public const string LERNENDER = 'lernender';

    /** @var array<string, array<string, string>> Rolle => Kartenschlüssel => Bezeichnung */
    public const array KARTEN = [
        self::ADMIN => [
            'handlungsbedarf' => 'Handlungsbedarf',
            'berufsbildner' => 'Berufsbildner',
            'aktivitaet' => 'Erfasste Noten pro Woche',
            'lehrende' => 'Lehrende bald',
        ],
        self::BERUFSBILDNER => [
            'aufmerksamkeit' => 'Braucht Aufmerksamkeit',
            'lernende' => 'Meine Lernenden',
            'agenda' => 'Nächste 14 Tage',
            'lehrende' => 'Lehrende bald',
        ],
        self::LERNENDER => [
            'stand' => 'Stand',
            'als_naechstes' => 'Als Nächstes',
            'wo_stehe_ich' => 'Wo stehe ich',
            'ziele' => 'Ziele',
            'verlauf' => 'Verlauf',
            'letzte_noten' => 'Letzte Noten',
        ],
    ];

    /**
     * Kartenschlüssel, die sich nicht ausblenden lassen (kein Häkchen im Profil, Versuch wird
     * abgewiesen): Handlungsbedarf/Aufmerksamkeit sind der eigentliche Zweck dieser Dashboards,
     * kein optionales Widget.
     */
    public const array IMMER_SICHTBAR = ['handlungsbedarf', 'aufmerksamkeit'];

    /**
     * Dashboard-Rolle des Benutzers (welches der drei Dashboards er sieht), oder null.
     * Gleiche Priorität wie die Weiterleitung in routes/web.php («/dashboard»): Admin vor
     * Berufsbildner vor Lernender – ein Admin mit zusätzlicher lernende-Zeile sieht sein
     * eigenes Dashboard, nicht das der Lernenden.
     */
    public static function rolleFuer(User $user): ?string
    {
        if ($user->hasRole('Admin')) {
            return self::ADMIN;
        }
        if ($user->hasRole('Berufsbildner')) {
            return self::BERUFSBILDNER;
        }
        if ($user->lernender) {
            return self::LERNENDER;
        }

        return null;
    }

    /**
     * @return array<string, string> Ausblendbare Kartenschlüssel => Bezeichnung, für die
     *                               Profil-Checkboxen und die Validierung. Ohne IMMER_SICHTBAR.
     */
    public static function fuerRolle(?string $rolle): array
    {
        return array_diff_key(self::KARTEN[$rolle] ?? [], array_flip(self::IMMER_SICHTBAR));
    }

    /**
     * @return list<string> Alle ausblendbaren Kartenschlüssel, über alle Rollen hinweg (Whitelist
     *                      beim Lesen gespeicherter Präferenzen) – ohne IMMER_SICHTBAR, damit eine
     *                      immer sichtbare Karte auch über eine direkt manipulierte Präferenz nicht
     *                      ausgeblendet werden kann.
     */
    public static function alleSchluessel(): array
    {
        $alle = array_merge(...array_values(array_map('array_keys', self::KARTEN)));

        return array_values(array_unique(array_diff($alle, self::IMMER_SICHTBAR)));
    }

    /** Ob die Karte mit diesem Schlüssel gerendert werden soll (Standard: sichtbar). */
    public static function sichtbar(array $ausgeblendet, string $schluessel): bool
    {
        return ! in_array($schluessel, $ausgeblendet, true);
    }
}
