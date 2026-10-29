<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

/**
 * Verteilungskennzahlen für Diagramme (Histogramm, Punktstreifen): Median, Quartile, Klassenzählung.
 * Quantile nach Typ 7 (lineare Interpolation, wie R quantile(type = 7)); unter drei Werten gibt es keine
 * Aussage (null). Gerechnet wird nur über übergebene, fertige Noten – Durchschnitte bleiben in Auswertung.
 */
final class Verteilung
{
    /** Kleinste Zahl von Werten, ab der Median und Quartile etwas aussagen. */
    public const int MINDESTANZAHL = 3;

    /** @param  list<float|int>  $werte */
    public static function median(array $werte): ?float
    {
        return self::quartile($werte)['q2'] ?? null;
    }

    /**
     * @param  list<float|int>  $werte
     * @return array{q1: float, q2: float, q3: float}|null
     */
    public static function quartile(array $werte): ?array
    {
        $sortiert = array_map('floatval', array_values($werte));
        if (count($sortiert) < self::MINDESTANZAHL) {
            return null;
        }
        sort($sortiert);

        return [
            'q1' => self::quantil($sortiert, 0.25),
            'q2' => self::quantil($sortiert, 0.5),
            'q3' => self::quantil($sortiert, 0.75),
        ];
    }

    /**
     * Zählt die Werte in Klassen [von, von + breite) … ; die letzte Klasse schliesst `bis` ein (Note 6.0).
     * Werte ausserhalb von von…bis zählen nicht und fliessen weder in n noch in den Median ein. Label einer
     * Klasse ist ihre Untergrenze mit zwei Stellen.
     *
     * @param  list<float|int>  $werte
     * @return array{labels: list<string>, werte: list<int>, untergrenzen: list<float>, breite: float, von: float, bis: float, n: int, median: ?float, quartile: array{q1: float, q2: float, q3: float}|null}
     */
    public static function histogramm(array $werte, float $breite = 0.25, float $von = 1.0, float $bis = 6.0): array
    {
        $klassen = max(1, (int) round(($bis - $von) / $breite));
        $untergrenzen = [];
        for ($i = 0; $i < $klassen; $i++) {
            $untergrenzen[] = round($von + $i * $breite, 4);
        }
        $zaehler = array_fill(0, $klassen, 0);

        $gezaehlt = [];
        foreach ($werte as $wert) {
            $wert = (float) $wert;
            if ($wert < $von - 1e-9 || $wert > $bis + 1e-9) {
                continue;
            }
            // 1e-9 wie Bericht::klasse und NotenSkala::stufe: ein float-«4.0» fällt nicht in die Klasse darunter
            $index = (int) floor(($wert + 1e-9 - $von) / $breite);
            $zaehler[min($klassen - 1, max(0, $index))]++;
            $gezaehlt[] = $wert;
        }

        return [
            'labels' => array_map(fn (float $u) => number_format($u, 2, '.', ''), $untergrenzen),
            'werte' => $zaehler,
            'untergrenzen' => $untergrenzen,
            'breite' => $breite,
            'von' => $von,
            'bis' => $bis,
            'n' => count($gezaehlt),
            'median' => self::median($gezaehlt),
            'quartile' => self::quartile($gezaehlt),
        ];
    }

    /** @param  list<float>  $sortiert  aufsteigend, mindestens ein Wert */
    private static function quantil(array $sortiert, float $p): float
    {
        $h = (count($sortiert) - 1) * $p;
        $unten = (int) floor($h);
        $oben = min($unten + 1, count($sortiert) - 1);
        $wert = $sortiert[$unten] + ($h - $unten) * ($sortiert[$oben] - $sortiert[$unten]);

        return (float) Rundung::auf($wert, 0.01);
    }
}
