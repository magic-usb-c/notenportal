<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use App\Services\Auswertung\Notenbaum\BaumRechner;

/**
 * Baut aus Leistungen die hierarchische Auswertung: Prüfung → Element (Fach×Semester | Modul)
 * → Kategorie → Gesamt, und darüber die Notenbäume des Lernenden (QV, Berufsmaturität).
 * Rein rechnerisch, ohne DB – siehe docs/notenlogik.md.
 */
final class Rechenkern
{
    /** @param  iterable<Leistung>  $leistungen */
    public function auswerten(iterable $leistungen, Konfiguration $k): Auswertung
    {
        $a = new Auswertung($k);
        $positionen = [];

        foreach ($leistungen as $l) {
            if ($l->istPosition()) {
                if (! $l->istUnbekannt()) {
                    $positionen[(int) $l->knotenId] = (float) $l->wert;
                }
                $a->positionen[] = $l;

                continue;
            }
            $semesterId = $l->semesterId ?? ($l->datum !== null ? $k->semesterFuerDatum($l->datum) : null);
            $schluessel = $l->schluessel($semesterId);
            if ($schluessel === null) {
                continue;
            }

            $a->elemente[$schluessel] ??= $l->fachId !== null
                ? new Element($schluessel, Element::FACH, $l->kategorieId, $l->fachId, null, $semesterId,
                    $k->fachName($l->fachId), zaehlt: $k->fachZaehlt($l->fachId))
                : new Element($schluessel, Element::MODUL, $l->kategorieId, null, $l->modulId, $semesterId,
                    $k->modulName((int) $l->modulId), $k->module[$l->modulId]['ziel'] ?? null);

            $element = $a->elemente[$schluessel];
            $element->leistungen[] = $l;

            // Modul zählt im Semester seiner letzten Prüfung
            if ($element->typ === Element::MODUL && $k->semesterSortierung($semesterId) >= $k->semesterSortierung($element->semesterId)) {
                $element->semesterId = $semesterId ?? $element->semesterId;
            }
        }

        foreach ($a->elemente as $element) {
            $this->elementBerechnen($element, $k);
        }

        $this->kategorienBerechnen($a, $k);
        $this->baeumeBerechnen($a, $k, $positionen);

        return $a;
    }

    /** @param  array<int, float>  $positionen */
    private function baeumeBerechnen(Auswertung $a, Konfiguration $k, array $positionen): void
    {
        if ($k->baeume === []) {
            return;
        }

        $rechner = new BaumRechner($a->elemente, $positionen, $k->genuegend);
        foreach ($k->baeume as $baum) {
            $a->baeume[$baum->id] = $rechner->rechnen($baum);
        }

        // Mit Baum des Lehrberufs ist dessen Wurzel die Gesamtnote; ohne Baum bleibt die Kategorie-Gewichtung.
        $haupt = $k->hauptbaum();
        if ($haupt !== null) {
            $wurzel = $a->baeume[$haupt->id]->wurzel();
            $a->gesamtSchnitt = $wurzel->schnitt;
            $a->gesamtNote = $wurzel->note;
        }
    }

    private function elementBerechnen(Element $e, Konfiguration $k): void
    {
        $gewichte = 0.0;
        $summe = 0.0;
        foreach ($e->leistungen as $l) {
            if ($l->istUnbekannt()) {
                continue;
            }
            $gewichte += $l->gewicht;
            $summe += $l->gewicht * $l->wert;
        }

        $e->gewichtSumme = $gewichte;
        $e->schnitt = $gewichte > 0 ? $summe / $gewichte : null;
        $e->note = Rundung::auf($e->schnitt, $k->rundungElement($e->kategorieId));
    }

    private function kategorienBerechnen(Auswertung $a, Konfiguration $k): void
    {
        $proKategorie = [];
        foreach ($a->elemente as $schluessel => $e) {
            $proKategorie[$e->kategorieId][] = $schluessel;
        }
        // Nicht zählende Fächer (IDAF, Sport) bleiben als Element sichtbar, gehen aber in keinen Schnitt ein.
        uksort($proKategorie, fn (int $x, int $y) => ($k->kategorien[$x]['sortierung'] ?? 0) <=> ($k->kategorien[$y]['sortierung'] ?? 0));

        $gewichtSumme = 0.0;
        $gewichtet = 0.0;

        foreach ($proKategorie as $kid => $schluessel) {
            $noten = [];
            foreach ($schluessel as $s) {
                if ($a->elemente[$s]->note !== null && $a->elemente[$s]->zaehlt) {
                    $noten[] = $a->elemente[$s]->note;
                }
            }
            $schnitt = Rundung::mittel($noten);
            $a->kategorien[$kid] = [
                'schnitt' => $schnitt,
                'note' => Rundung::auf($schnitt, $k->rundungSchnitt($kid)),
                'elemente' => $schluessel,
            ];

            $g = $k->kategorien[$kid]['gewicht_gesamt'] ?? 1.0;
            if ($schnitt !== null && $g > 0) {
                $gewichtSumme += $g;
                $gewichtet += $g * $schnitt;
            }
        }

        $a->gesamtSchnitt = $gewichtSumme > 0 ? $gewichtet / $gewichtSumme : null;
        $a->gesamtNote = Rundung::auf($a->gesamtSchnitt, $k->rundungGesamt);
    }
}
