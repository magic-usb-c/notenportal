<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use App\Support\Einstellungen;
use App\Support\Lehrsemester;
use App\Support\NotenSkala;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Berechnet Lernstände für viele Lernende gebündelt (wenige Queries, Rechenkern pro Person).
 */
final class LernstandRechner
{
    public function __construct(private readonly NotenQuelle $quelle) {}

    /**
     * @param  list<int>  $lernenderIds
     * @return array<int, Lernstand>
     */
    public function fuer(array $lernenderIds): array
    {
        if ($lernenderIds === []) {
            return [];
        }

        $auswertungen = $this->quelle->auswertungen($lernenderIds);
        $letzte = DB::table('noten')->whereIn('lernender_id', $lernenderIds)->whereNull('geloescht_am')
            ->groupBy('lernender_id')->selectRaw('lernender_id, MAX(pruefungsdatum) as letzte')->pluck('letzte', 'lernender_id');
        $ueberfaellig = DB::table('pruefungen')->whereIn('lernender_id', $lernenderIds)->whereNull('note_id')->whereNull('abgesagt_am')->where('datum', '<', now()->toDateString())
            ->groupBy('lernender_id')->selectRaw('lernender_id, COUNT(*) as anzahl')->pluck('anzahl', 'lernender_id');
        $lehrbeginn = DB::table('lernende')->whereIn('lernender_id', $lernenderIds)->pluck('lehrbeginn', 'lernender_id');
        // In einem Rutsch vorladen statt pro Person: verhindert N+1 bei Lehrsemester::nummer()
        // (personalisierte Semesternamen), das verlauf()/toArray() weiter unten je Person nutzt.
        Lehrsemester::vorladen($lehrbeginn->all());

        $out = [];
        foreach ($lernenderIds as $id) {
            $out[$id] = $this->berechne(
                $id,
                $auswertungen[$id],
                isset($letzte[$id]) ? Carbon::parse($letzte[$id]) : null,
                (int) ($ueberfaellig[$id] ?? 0),
                isset($lehrbeginn[$id]) ? Carbon::parse($lehrbeginn[$id]) : null,
            );
        }

        return $out;
    }

    public function berechne(int $id, Auswertung $a, ?Carbon $letzte, int $ueberfaellig, ?Carbon $lehrbeginn): Lernstand
    {
        $a->lernenderId ??= $id;
        $k = $a->konfiguration;
        $grenze = $k->genuegend;
        $semesterIds = $a->semesterIds();
        $aktuell = $k->semesterFuerDatum(now()->toDateString());
        $bezug = in_array($aktuell, $semesterIds, true) ? $aktuell : (end($semesterIds) ?: null);
        $pos = $bezug !== null ? array_search($bezug, $semesterIds, true) : false;
        $vorher = $pos !== false && $pos > 0 ? $semesterIds[$pos - 1] : null;

        $semesterNote = $bezug !== null ? $a->semester($bezug)['note'] : null;
        $vorsemesterNote = $vorher !== null ? $a->semester($vorher)['note'] : null;

        $ungenuegend = $bezug !== null
            ? array_values(array_filter($a->semester($bezug)['elemente'], fn (Element $e) => $e->note < $grenze - 1e-9))
            : [];

        $promotion = [];
        $einbrueche = [];
        if ($bezug !== null) {
            foreach (array_keys($a->kategorien) as $kid) {
                $p = $a->promotion($kid, $bezug);
                if ($p !== null && ! $p['erfuellt']) {
                    $promotion[] = ['kategorie_id' => $kid, 'kategorie' => $k->kategorieName($kid), 'verletzt' => $p['verletzt']];
                }
            }
            if ($vorher !== null) {
                foreach ($a->elemente as $e) {
                    if ($e->typ !== Element::FACH || $e->semesterId !== $bezug || $e->note === null) {
                        continue;
                    }
                    $alt = $a->elemente["f{$e->fachId}s{$vorher}"] ?? null;
                    if ($alt?->note !== null && $e->note - $alt->note <= -0.5) {
                        $einbrueche[] = ['label' => $e->label, 'vorher' => $alt->note, 'nachher' => $e->note];
                    }
                }
            }
        }

        $frist = (int) Einstellungen::get(Einstellungen::FRIST_INAKTIV_TAGE, '30');
        $tage = $letzte ? (int) $letzte->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;
        $delta = $semesterNote !== null && $vorsemesterNote !== null ? $semesterNote - $vorsemesterNote : null;

        $rot = [];
        $gelb = [];
        foreach ($promotion as $p) {
            $rot[] = __('Promotion :kategorie gefährdet', ['kategorie' => $p['kategorie']]);
        }
        if ($semesterNote !== null && $semesterNote < $grenze - 1e-9) {
            $rot[] = __('Semesterschnitt :note', ['note' => NotenSkala::format($semesterNote)]);
        }
        if (count($ungenuegend) >= 2) {
            $rot[] = __(':anzahl ungenügende Noten', ['anzahl' => count($ungenuegend)]);
        } elseif (count($ungenuegend) === 1) {
            $gelb[] = $ungenuegend[0]->label.' '.NotenSkala::format($ungenuegend[0]->note);
        }
        foreach ($einbrueche as $e) {
            $gelb[] = $e['label'].' '.NotenSkala::format($e['vorher']).' → '.NotenSkala::format($e['nachher']);
        }
        if ($delta !== null && $delta <= -0.3) {
            $gelb[] = __(':delta zum Vorsemester', ['delta' => NotenSkala::format($delta, 1)]);
        }
        if ($ueberfaellig > 0) {
            $gelb[] = $ueberfaellig === 1 ? __('1 Note fehlt') : __(':anzahl Noten fehlen', ['anzahl' => $ueberfaellig]);
        }
        if ($tage !== null && $tage > $frist) {
            $gelb[] = __('seit :tage Tagen keine Note', ['tage' => $tage]);
        }
        if ($letzte === null && $lehrbeginn !== null && $lehrbeginn->diffInDays(now(), false) > $frist) {
            $gelb[] = __('noch keine Noten');
        }

        $status = $rot !== [] ? Lernstand::ROT : ($gelb !== [] ? Lernstand::GELB : Lernstand::GRUEN);

        return new Lernstand(
            lernenderId: $id,
            auswertung: $a,
            semesterId: $bezug,
            semesterNote: $semesterNote,
            vorsemesterNote: $vorsemesterNote,
            verlauf: array_column($a->verlauf(), 'note'),
            ungenuegend: $ungenuegend,
            promotion: $promotion,
            einbrueche: $einbrueche,
            letztePruefung: $letzte,
            ueberfaellig: $ueberfaellig,
            status: $status,
            gruende: [...$rot, ...$gelb],
        );
    }
}
