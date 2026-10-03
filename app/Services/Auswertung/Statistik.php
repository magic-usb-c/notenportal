<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use Illuminate\Support\Carbon;

/**
 * Reihen und Vergleiche für die Statistikseiten. Gerechnet wird ausschliesslich über Rechenkern, Auswertung
 * und Zielrechner (docs/notenlogik.md); hier wird nur zugeschnitten, sortiert und beschriftet.
 */
final class Statistik
{
    /** Mehr Monatsenden als das werden zu Quartalsenden vergröbert. */
    public const int MAX_STICHTAGE = 60;

    public function __construct(
        private readonly Rechenkern $kern = new Rechenkern,
        private readonly Zielrechner $zielrechner = new Zielrechner,
    ) {}

    /**
     * Wert einer Zielgrösse an Monatsenden (oder Quartalsenden): Stand der Auswertung mit allen Leistungen,
     * die bis dahin datiert sind. Der erste Stichtag ist das erste Periodenende ab $von; ist $bis kein
     * Periodenende, ist $bis der letzte. Leistungen ohne Datum (IPA, Schlussprüfung, Notenbaum-Positionen,
     * auch noch offene) stehen nicht in der Reihe, sondern in meta.ohneDatum.
     *
     * @param  list<Leistung>  $leistungen
     * @return array{
     *     reihe: list<array{stichtag: string, wert: ?float}>,
     *     punkte: list<array{datum: string, note: float, fach: string, id: ?int, titel: ?string}>,
     *     meta: array{vergroebert: bool, stichtage: int, von: string, bis: string, ohneDatum: list<array{id: ?int, titel: ?string, knotenId: ?int, wert: ?float}>}
     * }
     */
    public function stichtagsreihe(array $leistungen, Konfiguration $k, Zielgroesse $z, Carbon $von, Carbon $bis): array
    {
        $von = $von->copy()->startOfDay();
        $bis = $bis->copy()->startOfDay();

        $mitDatum = [];
        $ohneDatum = [];
        foreach ($leistungen as $l) {
            if ($l->datum === null) {
                // auch offene Positionen (wert null): die Liste unter dem Verlauf zeigt, was noch aussteht
                $ohneDatum[] = ['id' => $l->id, 'titel' => $l->titel, 'knotenId' => $l->knotenId, 'wert' => $l->wert];

                continue;
            }
            $mitDatum[] = $l;
        }

        [$stichtage, $vergroebert] = $this->stichtage($von, $bis);

        $reihe = [];
        foreach ($stichtage as $tag) {
            $grenze = $tag->toDateString();
            $bekannt = array_values(array_filter($mitDatum, fn (Leistung $l) => substr($l->datum, 0, 10) <= $grenze));
            $reihe[] = ['stichtag' => $grenze, 'wert' => $this->kern->auswerten($bekannt, $k)->wert($z)];
        }

        $punkte = [];
        foreach ($mitDatum as $l) {
            $datum = substr($l->datum, 0, 10);
            if ($l->istUnbekannt() || $l->quelle !== Leistung::NOTE || $datum < $von->toDateString() || $datum > $bis->toDateString()
                || ! $this->gehoertZu($l, $z, $k)) {
                continue;
            }
            $punkte[] = ['datum' => $datum, 'note' => (float) $l->wert, 'fach' => $this->name($l, $k), 'id' => $l->id, 'titel' => $l->titel];
        }
        usort($punkte, fn (array $a, array $b) => [$a['datum'], $a['id']] <=> [$b['datum'], $b['id']]);

        return [
            'reihe' => $reihe,
            'punkte' => $punkte,
            'meta' => [
                'vergroebert' => $vergroebert,
                'stichtage' => count($stichtage),
                'von' => $von->toDateString(),
                'bis' => $bis->toDateString(),
                'ohneDatum' => $ohneDatum,
            ],
        ];
    }

    /**
     * Vorsemester gegen aktuelles Semester je Fach (und Modul): Zeugnisnoten aus Auswertung::semester, nach
     * Veränderung sortiert – grösster Rückgang zuerst. Wer nicht in beiden Semestern eine Note hat, steht
     * am Ende mit delta null.
     *
     * @return list<array{schluessel: string, fach: string, kategorie_id: int, vorher: ?float, jetzt: ?float, delta: ?float}>
     */
    /** Veränderung zweier Noten, eine Rundungsregel für Hanteln, Lernstand und Personenvergleich. */
    public static function delta(?float $jetzt, ?float $vorher): ?float
    {
        return $jetzt !== null && $vorher !== null ? round($jetzt - $vorher, 2) : null;
    }

    public function hanteln(Auswertung $a, int $semesterId, ?int $vorsemesterId, ?int $kategorieId = null): array
    {
        $jetzt = $this->nachSchluessel($a->semester($semesterId, $kategorieId)['elemente']);
        $vorher = $vorsemesterId !== null ? $this->nachSchluessel($a->semester($vorsemesterId, $kategorieId)['elemente']) : [];

        $zeilen = [];
        foreach ($jetzt + $vorher as $schluessel => $element) {
            $j = isset($jetzt[$schluessel]) ? $jetzt[$schluessel]->note : null;
            $v = isset($vorher[$schluessel]) ? $vorher[$schluessel]->note : null;
            $zeilen[] = [
                'schluessel' => $schluessel,
                'fach' => $element->label,
                'kategorie_id' => $element->kategorieId,
                'vorher' => $v,
                'jetzt' => $j,
                'delta' => self::delta($j, $v),
            ];
        }

        usort($zeilen, function (array $x, array $y) {
            if (($x['delta'] === null) !== ($y['delta'] === null)) {
                return $x['delta'] === null ? 1 : -1;
            }

            return [$x['delta'] ?? 0, mb_strtolower($x['fach'])] <=> [$y['delta'] ?? 0, mb_strtolower($y['fach'])];
        });

        return $zeilen;
    }

    /**
     * Welche Note braucht jede offene Position und jede kommende Prüfung für «genügend»? Offene Leistungen
     * mit derselben Zielgrösse (Prüfung → Zeugnisnote ihres Fachs im Semester bzw. Moduls, Position → Gesamtnote)
     * werden wie im Zielrechner gemeinsam gelöst: dieselbe Note in jeder dieser Prüfungen. Die Grenze kommt aus
     * der Konfiguration (Einstellung «genügend»), nicht aus dem Code.
     *
     * @param  list<Leistung>  $leistungen  erfasste und offene (wert null) Leistungen eines Lernenden
     * @return list<array{id: ?int, titel: ?string, datum: ?string, knotenId: ?int, ziel: string, grenze: float, status: string, note: ?float, aktuell: ?float, minimum: ?float, maximum: ?float, unbekannte: int}>
     */
    public function benoetigt(array $leistungen, Konfiguration $k, ?float $zielwert = null): array
    {
        $zielwert ??= $k->genuegend;

        $ziele = [];
        foreach ($leistungen as $l) {
            if ($l->istUnbekannt() && ($ziel = $this->zielFuer($l, $k)) !== null) {
                $ziele[(string) $ziel] = $ziel;
            }
        }

        $loesungen = [];
        foreach ($ziele as $schluessel => $ziel) {
            $loesungen[$schluessel] = $this->zielrechner->loese($leistungen, $ziel, $zielwert, $k);
        }

        $out = [];
        foreach ($leistungen as $l) {
            $ziel = $l->istUnbekannt() ? $this->zielFuer($l, $k) : null;
            if ($ziel === null) {
                continue;
            }
            $r = $loesungen[(string) $ziel];
            $out[] = [
                'id' => $l->id,
                'titel' => $l->titel,
                'datum' => $l->datum !== null ? substr($l->datum, 0, 10) : null,
                'knotenId' => $l->knotenId,
                'ziel' => (string) $ziel,
                'grenze' => $zielwert,
                'status' => $r['status'],
                'note' => $r['note'],
                'aktuell' => $r['aktuell'],
                'minimum' => $r['minimum'],
                'maximum' => $r['maximum'],
                'unbekannte' => $r['unbekannte'],
            ];
        }

        // Datierte zuerst (nächste Prüfung oben), Positionen ohne Datum danach; stabil innerhalb eines Datums
        usort($out, fn (array $x, array $y) => [$x['datum'] === null, $x['datum'] ?? ''] <=> [$y['datum'] === null, $y['datum'] ?? '']);

        return $out;
    }

    /** @return array{0: list<Carbon>, 1: bool} Stichtage und ob auf Quartale vergröbert wurde */
    private function stichtage(Carbon $von, Carbon $bis): array
    {
        if ($bis->lt($von)) {
            return [[], false];
        }

        $monatlich = $this->mitEnde($this->periodenenden($von, $bis, 1), $bis);
        if (count($monatlich) <= self::MAX_STICHTAGE) {
            return [$monatlich, false];
        }

        return [$this->mitEnde($this->periodenenden($von, $bis, 3), $bis), true];
    }

    /**
     * @param  list<Carbon>  $tage
     * @return list<Carbon>
     */
    private function mitEnde(array $tage, Carbon $bis): array
    {
        if ($tage === [] || $tage[array_key_last($tage)]->lt($bis)) {
            $tage[] = $bis->copy();
        }

        return $tage;
    }

    /** @return list<Carbon> Monats- (1) bzw. Quartalsenden (3) von … bis, beide inklusive */
    private function periodenenden(Carbon $von, Carbon $bis, int $monate): array
    {
        $out = [];
        $tag = $von->copy()->endOfMonth()->startOfDay();
        while ($tag->lte($bis)) {
            if ($monate === 1 || $tag->month % 3 === 0) {
                $out[] = $tag->copy();
            }
            $tag = $tag->copy()->addMonthNoOverflow()->endOfMonth()->startOfDay();
        }

        return $out;
    }

    /** Gehört die Leistung zur Zielgrösse (für die Punkte im Verlauf)? */
    private function gehoertZu(Leistung $l, Zielgroesse $z, Konfiguration $k): bool
    {
        $semester = $l->semesterId ?? ($l->datum !== null ? $k->semesterFuerDatum(substr($l->datum, 0, 10)) : null);

        return match ($z->ebene) {
            'gesamt' => true,
            'kategorie' => $l->kategorieId === $z->id && ($z->semesterId === null || $semester === $z->semesterId),
            'semester' => $semester === $z->id,
            'fach' => $l->fachId === $z->id && ($z->semesterId === null || $semester === $z->semesterId),
            'modul' => $l->modulId === $z->id,
        };
    }

    private function name(Leistung $l, Konfiguration $k): string
    {
        return match (true) {
            $l->fachId !== null => $k->fachName($l->fachId),
            $l->modulId !== null => $k->modulName($l->modulId),
            default => (string) $l->titel,
        };
    }

    private function zielFuer(Leistung $l, Konfiguration $k): ?Zielgroesse
    {
        if ($l->istPosition()) {
            return new Zielgroesse('gesamt');
        }
        if ($l->fachId !== null) {
            $semester = $l->semesterId ?? ($l->datum !== null ? $k->semesterFuerDatum(substr($l->datum, 0, 10)) : null);

            return new Zielgroesse('fach', $l->fachId, $semester);
        }
        if ($l->modulId !== null) {
            return new Zielgroesse('modul', $l->modulId);
        }

        return null;
    }

    /**
     * @param  list<Element>  $elemente
     * @return array<string, Element>
     */
    private function nachSchluessel(array $elemente): array
    {
        $out = [];
        foreach ($elemente as $e) {
            $out[$e->typ === Element::FACH ? 'f'.$e->fachId : 'm'.$e->modulId] = $e;
        }

        return $out;
    }
}
