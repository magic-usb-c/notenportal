<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use App\Services\Auswertung\Notenbaum\BaumRechner;
use App\Services\Auswertung\Notenbaum\Knoten;

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
     * «unbekannte» zählt nur die offenen Prüfungen, die in das Ziel eingehen: mit Ziel «Deutsch» ist die
     * offene Mathematikprüfung für den Satz «… in jeder der n offenen Prüfungen» ohne Belang.
     *
     * @param  list<Leistung>  $leistungen  unbekannte = wert null
     * @return array{status: string, note: ?float, resultat: ?float, minimum: ?float, maximum: ?float, aktuell: ?float, unbekannte: int}
     */
    public function loese(array $leistungen, Zielgroesse $ziel, float $zielwert, Konfiguration $k): array
    {
        $unbekannte = count(array_filter($leistungen, fn (Leistung $l) => $l->istUnbekannt()));
        $aktuell = $this->kern->auswerten($leistungen, $k)->wert($ziel);
        $antwort = fn (string $status, ?float $note = null, ?float $resultat = null, ?float $min = null, ?float $max = null) => [
            'status' => $status, 'note' => $note, 'resultat' => $resultat, 'minimum' => $min, 'maximum' => $max,
            'aktuell' => $aktuell, 'unbekannte' => $unbekannte > 0 ? $this->einfliessende($leistungen, $ziel, $k) : 0,
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

    /**
     * Anzahl offener Prüfungen, die in die Zielgrösse eingehen – strukturell über Element, Kategorie,
     * Semester und Notenbaum bestimmt, nicht durch Probieren: Rundungen auf Zwischenebenen verschlucken
     * sonst die Wirkung einzelner Prüfungen, die gemeinsam das Ziel sehr wohl verschieben.
     *
     * @param  list<Leistung>  $leistungen
     */
    private function einfliessende(array $leistungen, Zielgroesse $ziel, Konfiguration $k): int
    {
        // Gefüllt rechnen, damit jedes Element samt Semester (Modul: das seiner letzten Prüfung) feststeht.
        $gefuellt = array_map(fn (Leistung $l) => $l->istUnbekannt() ? $l->mitWert(6.0) : $l, $leistungen);
        $elementVon = [];
        foreach ($this->kern->auswerten($gefuellt, $k)->elemente as $e) {
            foreach ($e->leistungen as $l) {
                $elementVon[spl_object_id($l)] = $e;
            }
        }
        $haupt = $ziel->ebene === 'gesamt' ? $k->hauptbaum() : null;
        $blaetter = $haupt !== null ? $this->tragendeBlaetter($haupt->wurzel) : null;
        // Eine Position ist ein einzelner Wert je Knoten, ihr Gewicht zählt nicht; ist sie schon erfasst, bleibt eine offene wirkungslos.
        $erfasst = [];
        foreach ($leistungen as $l) {
            if ($l->istPosition() && ! $l->istUnbekannt()) {
                $erfasst[(int) $l->knotenId] = true;
            }
        }

        $anzahl = 0;
        foreach ($leistungen as $i => $l) {
            $wirkt = $l->istPosition() ? ! isset($erfasst[(int) $l->knotenId]) : $l->gewicht > 0;
            if ($l->istUnbekannt() && $wirkt
                && $this->fliesstEin($l, $elementVon[spl_object_id($gefuellt[$i])] ?? null, $ziel, $k, $blaetter)) {
                $anzahl++;
            }
        }

        return $anzahl;
    }

    /** @param  list<Knoten>|null  $blaetter  Blätter des Hauptbaums, die die Gesamtnote tragen (null: ohne Baum) */
    private function fliesstEin(Leistung $l, ?Element $e, Zielgroesse $ziel, Konfiguration $k, ?array $blaetter): bool
    {
        if ($blaetter !== null) {
            foreach ($blaetter as $b) {
                if ($b->typ === Knoten::MANUELL ? $l->knotenId === $b->id : ($e !== null && BaumRechner::nimmtAuf($b, $e))) {
                    return true;
                }
            }

            return false;
        }
        if ($e === null) {
            return false;
        }

        return match ($ziel->ebene) {
            'gesamt' => $e->zaehlt && ($k->kategorien[$e->kategorieId]['gewicht_gesamt'] ?? 1.0) > 0,
            'kategorie' => $e->zaehlt && $e->kategorieId === $ziel->id && ($ziel->semesterId === null || $e->semesterId === $ziel->semesterId),
            'semester' => $e->zaehlt && $e->semesterId === $ziel->id,
            'fach' => $e->typ === Element::FACH && $e->fachId === $ziel->id && ($ziel->semesterId === null || $e->semesterId === $ziel->semesterId),
            'modul' => $e->typ === Element::MODUL && $e->modulId === $ziel->id,
        };
    }

    /**
     * Blätter, deren Noten bis zur Wurzel durchschlagen: nur über zählende Teile mit Gewicht.
     *
     * @return list<Knoten>
     */
    private function tragendeBlaetter(Knoten $knoten): array
    {
        if ($knoten->istBlatt()) {
            return [$knoten];
        }
        $out = [];
        foreach ($knoten->kinder as $kind) {
            if ($kind->zaehlt && $kind->gewicht > 0) {
                array_push($out, ...$this->tragendeBlaetter($kind));
            }
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
