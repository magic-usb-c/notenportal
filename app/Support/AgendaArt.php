<?php

namespace App\Support;

/**
 * Darstellung der Eintragsarten der Agenda. Eine Quelle für Liste, Monatsraster, Tagesansicht und Legende,
 * damit Punkt und Bezeichnung überall gleich sind.
 */
final class AgendaArt
{
    public const ARTEN = ['pruefung', 'erkannt', 'termin', 'lektion'];

    /** Tailwind-Klassen des Kennpunkts: Akzent für Prüfungen, Grau für übrige Termine; Ring, wenn nur eingelesen. */
    public static function punkt(string $art): string
    {
        return match ($art) {
            'pruefung' => 'bg-accent',
            'erkannt' => 'border-2 border-accent',
            'termin' => 'bg-muted',
            default => 'border-2 border-muted',
        };
    }

    public static function label(string $art): string
    {
        return match ($art) {
            'pruefung' => __('Prüfungen'),
            'erkannt' => __('Erkannt, nicht zugeordnet'),
            'termin' => __('Schulnetz-Termine'),
            default => __('Stundenplan'),
        };
    }
}
