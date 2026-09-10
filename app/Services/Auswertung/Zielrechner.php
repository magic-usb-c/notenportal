<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

/**
 * Rückwärtsrechnung: welche Note x brauchen alle unbekannten Prüfungen, damit eine Zielgrösse
 * (gerundet) mindestens den Zielwert erreicht? Monoton in x, deshalb Suche im 0.05er-Raster.
 */
final class Zielrechner
{
    public const string BENOETIGT = 'benoetigt';

    public const string ERREICHT = 'erreicht';

    public const string UNERREICHBAR = 'unerreichbar';

    public const string OHNE_EINFLUSS = 'ohne_einfluss';

    public const string KEINE_UNBEKANNTEN = 'keine_unbekannten';

    public function __construct(private readonly Rechenkern $kern = new Rechenkern) {}

    /**
     * @param  list<Leistung>  $leistungen  unbekannte = wert null
     * @return array{status: string, note: ?float, resultat: ?float, minimum: ?float, maximum: ?float, aktuell: ?float, unbekannte: int}
     */
    public function loese(array $leistungen, Zielgroesse $ziel, float $zielwert, Konfiguration $k): array
    {
        $unbekannte = count(array_filter($leistungen, fn (Leistung $l) => $l->istUnbekannt()));
        $aktuell = $this->kern->auswerten($leistungen, $k)->wert($ziel);
        $antwort = fn (string $status, ?float $note = null, ?float $resultat = null, ?float $min = null, ?float $max = null) => [
            'status' => $status, 'note' => $note, 'resultat' => $resultat, 'minimum' => $min, 'maximum' => $max,
            'aktuell' => $aktuell, 'unbekannte' => $unbekannte,
        ];

        if ($unbekannte === 0) {
            return $antwort(self::KEINE_UNBEKANNTEN, resultat: $aktuell);
        }

        $minimum = $this->wertBei($leistungen, 1.0, $ziel, $k);
        $maximum = $this->wertBei($leistungen, 6.0, $ziel, $k);

        if ($minimum !== null && $minimum >= $zielwert - 1e-9) {
            return $antwort(self::ERREICHT, 1.0, $minimum, $minimum, $maximum);
        }
        if ($maximum === null || $minimum === $maximum) {
            return $antwort(self::OHNE_EINFLUSS, resultat: $maximum, min: $minimum, max: $maximum);
        }
        if ($maximum < $zielwert - 1e-9) {
            return $antwort(self::UNERREICHBAR, resultat: $maximum, min: $minimum, max: $maximum);
        }

        // kleinstes x im 0.05er-Raster, binär (Wert ist monoton steigend in x)
        $lo = 20;
        $hi = 120;
        while ($lo < $hi) {
            $mitte = intdiv($lo + $hi, 2);
            $w = $this->wertBei($leistungen, $mitte / 20, $ziel, $k);
            if ($w !== null && $w >= $zielwert - 1e-9) {
                $hi = $mitte;
            } else {
                $lo = $mitte + 1;
            }
        }

        $note = $lo / 20;

        return $antwort(self::BENOETIGT, $note, $this->wertBei($leistungen, $note, $ziel, $k), $minimum, $maximum);
    }

    /**
     * Ergebnis der Zielgrösse, wenn alle Unbekannten dieselbe Note bekommen (für die Kurve im Rechner).
     *
     * @param  list<Leistung>  $leistungen
     * @return list<array{x: float, wert: ?float}>
     */
    public function kurve(array $leistungen, Zielgroesse $ziel, Konfiguration $k, float $schritt = 0.25): array
    {
        $out = [];
        for ($i = 0; 1.0 + $i * $schritt <= 6.0 + 1e-9; $i++) {
            $x = round(1.0 + $i * $schritt, 2);
            $out[] = ['x' => $x, 'wert' => $this->wertBei($leistungen, $x, $ziel, $k)];
        }

        return $out;
    }

    /** @param  list<Leistung>  $leistungen */
    public function wertBei(array $leistungen, float $x, Zielgroesse $ziel, Konfiguration $k): ?float
    {
        $gefuellt = array_map(fn (Leistung $l) => $l->istUnbekannt() ? $l->mitWert($x) : $l, $leistungen);

        return $this->kern->auswerten($gefuellt, $k)->wert($ziel);
    }
}
