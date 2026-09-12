<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Auswertung\Konfiguration;

/**
 * Betriebseinstellungen (Name, Notengrenzen, Rundung Gesamtschnitt, Fristen) – Seite «Betrieb» und Einrichtung.
 */
final class Betrieb
{
    /** Schlüssel => Standardwert */
    public const array FELDER = [
        Einstellungen::BETRIEB_NAME => '',
        Einstellungen::NOTE_GUT => '5.0',
        Einstellungen::NOTE_GENUEGEND => '4.0',
        Einstellungen::NOTE_KRITISCH => '3.5',
        Einstellungen::RUNDUNG_GESAMT => '0.1',
        Einstellungen::FRIST_INAKTIV_TAGE => '30',
        Einstellungen::FRIST_LEHRENDE_TAGE => '60',
    ];

    public const array RUNDUNGEN_GESAMT = ['0.01', '0.05', '0.1', '0.25', '0.5'];

    /** @return array<string, string> */
    public static function werte(): array
    {
        $werte = [];
        foreach (self::FELDER as $schluessel => $standard) {
            $werte[$schluessel] = (string) Einstellungen::get($schluessel, $standard);
        }

        return $werte;
    }

    /** @return array<string, list<string>> */
    public static function regeln(): array
    {
        return [
            Einstellungen::BETRIEB_NAME => ['required', 'string', 'max:120'],
            Einstellungen::NOTE_GUT => ['required', 'numeric', 'between:1,6', 'multiple_of:0.05', 'gt:'.Einstellungen::NOTE_GENUEGEND],
            Einstellungen::NOTE_GENUEGEND => ['required', 'numeric', 'between:1,6', 'multiple_of:0.05', 'gt:'.Einstellungen::NOTE_KRITISCH],
            Einstellungen::NOTE_KRITISCH => ['required', 'numeric', 'between:1,6', 'multiple_of:0.05'],
            Einstellungen::RUNDUNG_GESAMT => ['required', 'in:'.implode(',', self::RUNDUNGEN_GESAMT)],
            Einstellungen::FRIST_INAKTIV_TAGE => ['required', 'integer', 'between:7,365'],
            Einstellungen::FRIST_LEHRENDE_TAGE => ['required', 'integer', 'between:7,365'],
        ];
    }

    public static function speichern(array $validiert): void
    {
        foreach (array_keys(self::FELDER) as $schluessel) {
            Einstellungen::set($schluessel, trim((string) $validiert[$schluessel]));
        }
        Konfiguration::vergessen();
        Lehrsemester::vergessen();
    }
}
