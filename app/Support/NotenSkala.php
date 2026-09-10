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
        self::GUT => 'text-green-700 dark:text-green-400',
        self::GENUEGEND => 'text-emerald-700 dark:text-emerald-400',
        self::KNAPP => 'text-yellow-700 dark:text-yellow-400',
        self::UNGENUEGEND => 'text-red-600 dark:text-red-400',
    ];

    private const array BALKEN = [
        self::GUT => 'bg-green-500',
        self::GENUEGEND => 'bg-emerald-500',
        self::KNAPP => 'bg-yellow-500',
        self::UNGENUEGEND => 'bg-red-500',
    ];

    private const array BADGE = [
        self::GUT => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        self::GENUEGEND => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        self::KNAPP => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        self::UNGENUEGEND => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
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
