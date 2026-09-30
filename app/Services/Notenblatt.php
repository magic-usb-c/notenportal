<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lernender;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\NotenQuelle;
use App\Support\Einstellungen;
use App\Support\NotenSkala;
use Illuminate\Support\Facades\DB;

/**
 * Druckbares Notenblatt: Zeugnisnoten je Semester und Kategorie mit den zugrunde liegenden Prüfungen.
 */
final class Notenblatt
{
    public function __construct(private readonly NotenQuelle $quelle) {}

    /** @return array<string, mixed> */
    public function fuer(Lernender $l): array
    {
        $l->loadMissing(['benutzer', 'lehrberuf']);
        $a = $this->quelle->auswertung((int) $l->lernender_id);
        $k = $a->konfiguration;

        $stufen = $this->stufen((int) $l->lernender_id);
        $semesterIds = array_values(array_unique([...$a->semesterIds(), ...array_keys($stufen)]));
        if (count($semesterIds) > count($a->semesterIds())) {
            $start = DB::table('semester')->whereIn('semester_id', $semesterIds)->pluck('start_datum', 'semester_id');
            usort($semesterIds, fn ($x, $y) => [(string) ($start[$x] ?? ''), $x] <=> [(string) ($start[$y] ?? ''), $y]);
        }

        $semester = [];
        foreach ($semesterIds as $sid) {
            $kategorien = [];
            foreach (array_keys($k->kategorien) as $kid) {
                $elemente = array_values(array_filter(
                    $a->elementeDerKategorie($kid),
                    fn (Element $e) => $e->semesterId === $sid && $e->note !== null
                ));
                $zeilen = [
                    ...array_map(fn (Element $e) => [
                        'label' => $e->label,
                        'note' => $e->note,
                        'stufe' => null,
                        'schnitt' => $e->schnitt,
                        'offen' => $e->offenGewicht(),
                        'pruefungen' => array_values(array_map(
                            fn (Leistung $p) => ['datum' => $p->datum, 'titel' => $p->titel, 'note' => $p->wert, 'stufe' => null, 'gewicht' => $p->gewicht],
                            array_filter($e->leistungen, fn (Leistung $p) => ! $p->istUnbekannt())
                        )),
                    ], $elemente),
                    ...($stufen[$sid][$kid] ?? []),
                ];
                if ($zeilen === []) {
                    continue;
                }
                $kategorien[] = [
                    'name' => $k->kategorieName($kid),
                    'note' => $a->semester($sid, $kid)['note'],
                    'promotion' => $a->promotion($kid, $sid),
                    'elemente' => $zeilen,
                ];
            }
            // Persönliches Notenblatt genau eines Lernenden -> relative Semesternummer statt neutralem Namen.
            $semester[] = ['bezeichnung' => $k->semesterName($sid, $a->lernenderId), 'note' => $a->semester($sid)['note'], 'kategorien' => $kategorien];
        }

        $kategorien = [];
        foreach ($a->kategorien as $kid => $kat) {
            if ($kat['note'] !== null) {
                $kategorien[] = ['name' => $k->kategorieName($kid), 'note' => $kat['note']];
            }
        }

        return [
            'name' => $l->benutzer->vorname.' '.$l->benutzer->nachname,
            'lehrberuf' => $l->lehrberuf?->name,
            'lehrbeginn' => $l->lehrbeginn,
            'lehrende' => $l->lehrende,
            'betrieb' => Einstellungen::get(Einstellungen::BETRIEB_NAME, ''),
            'gesamt' => $a->gesamtNote,
            'kategorien' => $kategorien,
            'semester' => $semester,
        ];
    }

    /**
     * Fächer mit Stufen (Sport A/B/C, dispensiert): rechnen nicht mit, stehen aber im Zeugnis. Je Semester
     * und Kategorie eine Zeile pro Fach; die Zeugnisstufe ist die zuletzt erfasste.
     *
     * @return array<int, array<int, list<array<string, mixed>>>> semester_id => kategorie_id => Zeilen
     */
    private function stufen(int $lernenderId): array
    {
        $noten = DB::table('noten as n')->join('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->where('n.lernender_id', $lernenderId)->whereNull('n.geloescht_am')->whereNotNull('n.note_stufe')
            ->orderBy('n.pruefungsdatum')->orderBy('n.note_id')
            ->get(['n.semester_id', 'n.pruefungsdatum', 'n.titel', 'n.note_stufe', 'f.fach_id', 'f.name', 'f.kategorie_id']);

        $out = [];
        foreach ($noten->groupBy(fn ($n) => $n->semester_id.':'.$n->fach_id) as $gruppe) {
            $letzte = $gruppe->last();
            $out[(int) $letzte->semester_id][(int) $letzte->kategorie_id][] = [
                'label' => $letzte->name,
                'note' => null,
                'stufe' => NotenSkala::stufeText($letzte->note_stufe),
                'schnitt' => null,
                'offen' => 0.0,
                'pruefungen' => $gruppe->map(fn ($n) => ['datum' => $n->pruefungsdatum, 'titel' => $n->titel, 'note' => null,
                    'stufe' => NotenSkala::stufeText($n->note_stufe), 'gewicht' => 100.0])->values()->all(),
            ];
        }

        return $out;
    }
}
