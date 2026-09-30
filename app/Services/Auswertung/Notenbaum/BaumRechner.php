<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use App\Services\Auswertung\Element;
use App\Services\Auswertung\Rundung;

/**
 * Wertet einen Notenbaum über den Elementen (Zeugnisnoten) eines Lernenden aus.
 *
 * Gruppe: gewichtetes Mittel der zählenden Unterknoten, die einen Wert haben (fehlende Positionen
 * werden bis zu ihrer Erfassung ausgelassen, die Note ist dann vorläufig). Jeder Knoten rundet nach
 * seiner eigenen Regel; nicht rundende Knoten geben ihren ungerundeten Wert weiter. Rundung also nur
 * dort, wo der Baum (und damit die Verordnung) sie vorsieht – siehe docs/notenlogik.md.
 */
final class BaumRechner
{
    /** @var list<Grund> */
    private array $gruende = [];

    /** @var array<string, KnotenErgebnis> */
    private array $ergebnisse = [];

    /**
     * @param  array<string, Element>  $elemente
     * @param  array<int, float>  $positionen  erfasste Werte je Knoten-ID
     */
    public function __construct(
        private readonly array $elemente,
        private readonly array $positionen,
        private readonly float $genuegend = 4.0,
    ) {}

    public function rechnen(Baum $baum): BaumErgebnis
    {
        $this->gruende = [];
        $this->ergebnisse = [];

        $wurzel = $this->knoten($baum->wurzel);
        $this->regelnPruefen($wurzel);

        $definitiv = array_filter($this->gruende, fn (Grund $g) => $g->definitiv) !== [];
        $status = match (true) {
            $wurzel->vollstaendig => $this->gruende === [] ? BaumErgebnis::BESTANDEN : BaumErgebnis::NICHT_BESTANDEN,
            $definitiv => BaumErgebnis::NICHT_BESTANDEN,
            default => BaumErgebnis::OFFEN,
        };

        return new BaumErgebnis($baum, $this->ergebnisse, $status, $this->gruende);
    }

    private function knoten(Knoten $k): KnotenErgebnis
    {
        $e = new KnotenErgebnis($k);
        // Vorab eintragen, damit die Reihenfolge der Baumreihenfolge folgt (Wurzel zuerst).
        $this->ergebnisse[$k->code] = $e;

        match ($k->typ) {
            Knoten::GRUPPE => $this->gruppe($e),
            Knoten::MANUELL => $this->blatt($e, isset($this->positionen[$k->id]) ? [$this->positionen[$k->id]] : []),
            Knoten::KATEGORIE => $this->blatt($e, $this->notenDerKategorie($k)),
            Knoten::FAECHER => $this->blatt($e, $this->notenDerFaecher($k)),
        };

        return $e;
    }

    /** @param  list<float>  $noten */
    private function blatt(KnotenErgebnis $e, array $noten): void
    {
        $e->anzahl = count($noten);
        $e->schnitt = Rundung::mittel($noten);
        $e->note = Rundung::auf($e->schnitt, $e->knoten->rundung ?? 0.0);
        $e->vollstaendig = $e->schnitt !== null;
    }

    private function gruppe(KnotenErgebnis $e): void
    {
        $summe = 0.0;
        $gewichte = 0.0;
        $vollstaendig = true;
        $zaehlende = 0;

        foreach ($e->knoten->kinder as $kind) {
            $k = $this->knoten($kind);
            $e->kinder[] = $k;
            if (! $kind->zaehlt) {
                continue;
            }
            $zaehlende++;
            $vollstaendig = $vollstaendig && $k->vollstaendig;
            if ($k->massgebend() !== null && $kind->gewicht > 0) {
                $summe += $kind->gewicht * $k->massgebend();
                $gewichte += $kind->gewicht;
            }
        }

        $e->anzahl = $zaehlende;
        $e->schnitt = $gewichte > 0 ? $summe / $gewichte : null;
        $e->note = Rundung::auf($e->schnitt, $e->knoten->rundung ?? 0.0);
        $e->vollstaendig = $vollstaendig && $zaehlende > 0 && $e->schnitt !== null;
    }

    /** @return list<float> Zeugnisnoten der zählenden Elemente einer Kategorie */
    private function notenDerKategorie(Knoten $k): array
    {
        $noten = [];
        foreach ($this->elemente as $el) {
            if ($el->kategorieId !== $k->kategorieId || ! $el->zaehlt || $el->note === null) {
                continue;
            }
            if (($k->elementtyp === Knoten::NUR_MODULE && $el->typ !== Element::MODUL)
                || ($k->elementtyp === Knoten::NUR_FAECHER && $el->typ !== Element::FACH)) {
                continue;
            }
            $noten[] = $el->note;
        }

        return $noten;
    }

    /**
     * Alle Semesterzeugnisnoten der genannten Fächer gemeinsam gemittelt. Ausdrücklich genannte Fächer
     * zählen auch dann, wenn sie sonst nicht in Schnitte eingehen (Erfahrungsnote IDAF für die IDPA).
     *
     * @return list<float>
     */
    private function notenDerFaecher(Knoten $k): array
    {
        $noten = [];
        foreach ($this->elemente as $el) {
            if ($el->typ === Element::FACH && $el->note !== null && in_array($el->fachId, $k->faecher, true)) {
                $noten[] = $el->note;
            }
        }

        return $noten;
    }

    private function regelnPruefen(KnotenErgebnis $e): void
    {
        $k = $e->knoten;
        if ($k->fallnote !== null && $e->note !== null && $e->note < $k->fallnote - 1e-9) {
            $this->gruende[] = new Grund($k->code, $k->name, Grund::FALLNOTE, $e->note, $k->fallnote, $k->typ === Knoten::MANUELL);
        }

        if ($k->maxUngenuegend !== null || $k->maxMinuspunkte !== null) {
            $ungenuegend = 0;
            $minuspunkte = 0.0;
            foreach ($e->kinder as $kind) {
                $note = $kind->massgebend();
                if (! $kind->knoten->zaehlt || $note === null || $note >= $this->genuegend - 1e-9) {
                    continue;
                }
                $ungenuegend++;
                $minuspunkte += $this->genuegend - $note;
            }
            $minuspunkte = round($minuspunkte, 2);

            if ($k->maxUngenuegend !== null && $ungenuegend > $k->maxUngenuegend) {
                $this->gruende[] = new Grund($k->code, $k->name, Grund::UNGENUEGEND, $ungenuegend, $k->maxUngenuegend);
            }
            if ($k->maxMinuspunkte !== null && $minuspunkte > $k->maxMinuspunkte + 1e-9) {
                $this->gruende[] = new Grund($k->code, $k->name, Grund::MINUSPUNKTE, $minuspunkte, $k->maxMinuspunkte);
            }
        }

        // Nicht zählende Teile (entfallen, nur informativ) entscheiden nicht über das Bestehen
        foreach ($e->kinder as $kind) {
            if ($kind->knoten->zaehlt) {
                $this->regelnPruefen($kind);
            }
        }
    }
}
