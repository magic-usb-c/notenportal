<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Einzige Stelle für Notenstufen und ihre Farben. Grenzen kommen aus den Einstellungen.
 */
final class NotenSkala
{
    public const string GUT = 'gut';

    public const string GENUEGEND = 'genuegend';

    public const string KNAPP = 'knapp';

    public const string UNGENUEGEND = 'ungenuegend';

    private const array TEXT = [
        self::GUT => 'text-note-gut',
        self::GENUEGEND => 'text-note-genuegend',
        self::KNAPP => 'text-note-knapp',
        self::UNGENUEGEND => 'text-note-ungenuegend',
    ];

    private const array BALKEN = [
        self::GUT => 'bg-note-gut',
        self::GENUEGEND => 'bg-note-genuegend',
        self::KNAPP => 'bg-note-knapp',
        self::UNGENUEGEND => 'bg-note-ungenuegend',
    ];

    /** Text in Notenfarbe auf 14 % Tint derselben Farbe (Kontrast in theme.css gerechnet). */
    private const array BADGE = [
        self::GUT => 'bg-note-gut/14 text-note-gut',
        self::GENUEGEND => 'bg-note-genuegend/14 text-note-genuegend',
        self::KNAPP => 'bg-note-knapp/14 text-note-knapp',
        self::UNGENUEGEND => 'bg-note-ungenuegend/14 text-note-ungenuegend',
    ];

    private const array GLOW = [
        self::GUT => 'np-text-glow-green',
        self::GENUEGEND => 'np-text-glow-emerald',
        self::KNAPP => 'np-text-glow-yellow',
        self::UNGENUEGEND => 'np-text-glow-red',
    ];

    /** @return array{gut: float, genuegend: float, kritisch: float} */
    public static function grenzen(): array
    {
        return [
            'gut' => (float) Einstellungen::get(Einstellungen::NOTE_GUT, '5.0'),
            'genuegend' => (float) Einstellungen::get(Einstellungen::NOTE_GENUEGEND, '4.0'),
            'kritisch' => (float) Einstellungen::get(Einstellungen::NOTE_KRITISCH, '3.5'),
        ];
    }

    public static function genuegend(): float
    {
        return self::grenzen()['genuegend'];
    }

    public static function stufe(float|string|null $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }
        $v = (float) $wert;
        $g = self::grenzen();

        return match (true) {
            $v >= $g['gut'] - 1e-9 => self::GUT,
            $v >= $g['genuegend'] - 1e-9 => self::GENUEGEND,
            $v >= $g['kritisch'] - 1e-9 => self::KNAPP,
            default => self::UNGENUEGEND,
        };
    }

    public static function text(float|string|null $wert): string
    {
        return self::TEXT[self::stufe($wert)] ?? 'text-muted';
    }

    public static function balken(float|string|null $wert): string
    {
        return self::BALKEN[self::stufe($wert)] ?? 'bg-muted/30';
    }

    public static function badge(float|string|null $wert): string
    {
        return self::BADGE[self::stufe($wert)] ?? 'bg-bg text-muted';
    }

    public static function glow(float|string|null $wert): string
    {
        return self::GLOW[self::stufe($wert)] ?? '';
    }

    /** Farbe einer benötigten Note nach Schwierigkeit: bis Genügend+0.5 gut machbar, ab Gut+0.25 schwer. */
    public static function bedarf(float|string|null $wert): string
    {
        if ($wert === null || $wert === '') {
            return 'text-muted';
        }
        $g = self::grenzen();

        return match (true) {
            (float) $wert <= $g['genuegend'] + 0.5 => self::TEXT[self::GUT],
            (float) $wert <= $g['gut'] + 0.25 => self::TEXT[self::KNAPP],
            default => self::TEXT[self::UNGENUEGEND],
        };
    }

    /** Balkenbreite in Prozent: Note 1 = 0 %, Note 6 = 100 %. */
    public static function breite(float|string|null $wert): float
    {
        return $wert === null || $wert === '' ? 0.0 : max(0.0, min(100.0, ((float) $wert - 1) / 5 * 100));
    }

    /** Note ohne überflüssige Nullen (4.25, 4.5, 5.0); Durchschnitte mit fester Stellenzahl. */
    public static function format(float|string|null $wert, ?int $stellen = null): string
    {
        if ($wert === null || $wert === '') {
            return '–';
        }
        if ($stellen !== null) {
            return number_format((float) $wert, $stellen, '.', '');
        }
        $s = number_format((float) $wert, 2, '.', '');

        return str_ends_with($s, '0') ? substr($s, 0, -1) : $s;
    }
}
