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

    /** @var list<Bedingung> */
    private array $bedingungen = [];

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
        $this->bedingungen = [];
        $this->ergebnisse = [];

        $wurzel = $this->knoten($baum->wurzel);
        $this->regelnPruefen($wurzel);

        $definitiv = array_filter($this->gruende, fn (Grund $g) => $g->definitiv) !== [];
        $status = match (true) {
            $wurzel->vollstaendig && $wurzel->note !== null => $this->gruende === [] ? BaumErgebnis::BESTANDEN : BaumErgebnis::NICHT_BESTANDEN,
            $definitiv => BaumErgebnis::NICHT_BESTANDEN,
            default => BaumErgebnis::OFFEN,
        };

        return new BaumErgebnis($baum, $this->ergebnisse, $status, $this->gruende, $this->bedingungen);
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
        $tragende = 0;
        // Zählt diese Gruppe ungenügende Teile oder Minuspunkte, entscheidet jeder zählende Teil mit.
        $zaehltUngenuegende = $e->knoten->maxUngenuegend !== null || $e->knoten->maxMinuspunkte !== null;

        foreach ($e->knoten->kinder as $kind) {
            $k = $this->knoten($kind);
            $e->kinder[] = $k;
            if (! $kind->zaehlt) {
                continue;
            }
            $zaehlende++;
            // Ein Teil ohne Gewicht (oder eine Gruppe ohne zählende Teile) bewegt die Note nicht. Offen hält
            // er sie nur, wenn er trotzdem übers Bestehen entscheidet (eigene Mindestnote, Regel darunter
            // oder hier gezählte ungenügende Teile) – sonst stünde «bestanden», bevor er erfasst ist.
            $bewegt = $kind->gewicht > 0 && ! $k->leer;
            if ($bewegt || $zaehltUngenuegende || self::hatRegel($kind)) {
                $vollstaendig = $vollstaendig && $k->vollstaendig;
            }
            if (! $bewegt) {
                continue;
            }
            $tragende++;
            if ($k->massgebend() !== null) {
                $summe += $kind->gewicht * $k->massgebend();
                $gewichte += $kind->gewicht;
            }
        }

        $e->anzahl = $zaehlende;
        $e->leer = $tragende === 0;
        $e->schnitt = $gewichte > 0 ? $summe / $gewichte : null;
        $e->note = Rundung::auf($e->schnitt, $e->knoten->rundung ?? 0.0);
        $e->vollstaendig = $vollstaendig && ($e->leer || $e->schnitt !== null);
    }

    /** Trägt der Knoten oder ein zählender Teil darunter eine Bestehensregel? */
    private static function hatRegel(Knoten $k): bool
    {
        if ($k->fallnote !== null || $k->maxUngenuegend !== null || $k->maxMinuspunkte !== null) {
            return true;
        }
        foreach ($k->kinder as $kind) {
            if ($kind->zaehlt && self::hatRegel($kind)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<float> Zeugnisnoten der zählenden Elemente einer Kategorie */
    private function notenDerKategorie(Knoten $k): array
    {
        $noten = [];
        foreach ($this->elemente as $el) {
            if ($el->note !== null && self::nimmtAuf($k, $el)) {
                $noten[] = $el->note;
            }
        }

        return $noten;
    }

    /**
     * Geht die Zeugnisnote dieses Elements in das Blatt ein? Einzige Stelle für die Zuordnung, damit der
     * Zielrechner dieselbe Antwort bekommt wie die Rechnung selbst.
     */
    public static function nimmtAuf(Knoten $k, Element $el): bool
    {
        return match ($k->typ) {
            Knoten::KATEGORIE => $el->kategorieId === $k->kategorieId && $el->zaehlt
                && ! ($k->elementtyp === Knoten::NUR_MODULE && $el->typ !== Element::MODUL)
                && ! ($k->elementtyp === Knoten::NUR_FAECHER && $el->typ !== Element::FACH),
            // Ausdrücklich genannte Fächer zählen auch dann, wenn sie sonst nicht in Schnitte eingehen.
            Knoten::FAECHER => $el->typ === Element::FACH && in_array($el->fachId, $k->faecher, true),
            default => false,
        };
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
            if ($el->note !== null && self::nimmtAuf($k, $el)) {
                $noten[] = $el->note;
            }
        }

        return $noten;
    }

    private function regelnPruefen(KnotenErgebnis $e): void
    {
        $k = $e->knoten;
        if ($k->fallnote !== null) {
            $verletzt = $e->note !== null && $e->note < $k->fallnote - 1e-9;
            $definitiv = $k->typ === Knoten::MANUELL;
            if ($verletzt) {
                $this->gruende[] = new Grund($k->code, $k->name, Grund::FALLNOTE, $e->note, $k->fallnote, $definitiv);
            }
            $this->bedingungen[] = new Bedingung($k->code, $k->name, Grund::FALLNOTE, $e->note, $k->fallnote,
                match (true) {
                    $e->note === null => Bedingung::OFFEN,
                    $verletzt => Bedingung::VERLETZT,
                    default => Bedingung::ERFUELLT,
                }, $definitiv && $e->note !== null);
        }

        if ($k->maxUngenuegend !== null || $k->maxMinuspunkte !== null) {
            $ungenuegend = 0;
            $minuspunkte = 0.0;
            $mitWert = 0;
            foreach ($e->kinder as $kind) {
                $note = $kind->massgebend();
                if (! $kind->knoten->zaehlt || $note === null) {
                    continue;
                }
                $mitWert++;
                if ($note >= $this->genuegend - 1e-9) {
                    continue;
                }
                $ungenuegend++;
                $minuspunkte += $this->genuegend - $note;
            }
            $minuspunkte = round($minuspunkte, 2);

            // Ohne einen einzigen Wert gibt es noch nichts zu zählen: offen, nicht «0 ungenügend».
            $stand = fn (bool $verletzt) => match (true) {
                $verletzt => Bedingung::VERLETZT,
                $mitWert === 0 => Bedingung::OFFEN,
                default => Bedingung::ERFUELLT,
            };
            if ($k->maxUngenuegend !== null) {
                $verletzt = $ungenuegend > $k->maxUngenuegend;
                if ($verletzt) {
                    $this->gruende[] = new Grund($k->code, $k->name, Grund::UNGENUEGEND, $ungenuegend, $k->maxUngenuegend);
                }
                $this->bedingungen[] = new Bedingung($k->code, $k->name, Grund::UNGENUEGEND, $mitWert === 0 ? null : $ungenuegend,
                    $k->maxUngenuegend, $stand($verletzt), false);
            }
            if ($k->maxMinuspunkte !== null) {
                $verletzt = $minuspunkte > $k->maxMinuspunkte + 1e-9;
                if ($verletzt) {
                    $this->gruende[] = new Grund($k->code, $k->name, Grund::MINUSPUNKTE, $minuspunkte, $k->maxMinuspunkte);
                }
                $this->bedingungen[] = new Bedingung($k->code, $k->name, Grund::MINUSPUNKTE, $mitWert === 0 ? null : $minuspunkte,
                    $k->maxMinuspunkte, $stand($verletzt), false);
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
