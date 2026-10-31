<?php

declare(strict_types=1);

namespace App\Services\Statistik;

use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Konfiguration;

/**
 * Zeugnisnoten als Balken (Stärken und Schwächen, Wo stehe ich). Gleichnamige Fächer verschiedener Kategorien
 * (Englisch im Fachunterricht und in der BMS) sind zwei Balken: gesammelt wird nach Fach bzw. Modul, nicht nach
 * Beschriftung, und bei doppeltem Namen hängt der Kategoriename an (wie Statistik::hanteln). Die Noten selbst
 * kommen aus der Auswertung.
 */
final class Zeugnisbalken
{
    /**
     * Fach- und Modulnoten über die Lehrzeit, schwächste zuerst (bei gleicher Note in Reihenfolge der Auswertung).
     *
     * @param  ?int  $kategorieId  nur diese Kategorie
     * @return list<array{label: string, wert: float}>
     */
    public static function lehrzeit(Auswertung $a, ?int $kategorieId = null): array
    {
        $eintraege = [];
        foreach ($a->elemente as $e) {
            if (! $e->zaehlt || ($kategorieId !== null && $e->kategorieId !== $kategorieId)) {
                continue;
            }
            if ($e->typ === Element::MODUL && $e->note !== null) {
                $eintraege['m'.$e->modulId] = ['element' => $e, 'wert' => $e->note];
            } elseif ($e->typ === Element::FACH && ! isset($eintraege['f'.$e->fachId])) {
                $note = $a->fach((int) $e->fachId)['note'];
                if ($note !== null) {
                    $eintraege['f'.$e->fachId] = ['element' => $e, 'wert' => $note];
                }
            }
        }

        $eintraege = array_values($eintraege);
        // usort ist stabil: gleiche Noten behalten die Reihenfolge der Auswertung
        usort($eintraege, fn (array $x, array $y) => $x['wert'] <=> $y['wert']);

        $labels = self::beschriften(array_column($eintraege, 'element'), $a->konfiguration);

        return array_map(fn (array $eintrag, string $label) => ['label' => $label, 'wert' => $eintrag['wert']], $eintraege, $labels);
    }

    /**
     * Beschriftungen in Reihenfolge der Elemente; nur bei tatsächlich doppeltem Namen mit « · Kategorie».
     *
     * @param  list<Element>  $elemente
     * @return list<string>
     */
    public static function beschriften(array $elemente, Konfiguration $k): array
    {
        $elemente = array_values($elemente);
        $anzahl = array_count_values(array_map(fn (Element $e) => mb_strtolower($e->label), $elemente));

        return array_map(
            fn (Element $e) => $anzahl[mb_strtolower($e->label)] > 1 ? $e->label.' · '.$k->kategorieName($e->kategorieId) : $e->label,
            $elemente,
        );
    }
}
