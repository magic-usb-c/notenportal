<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use Illuminate\Support\Facades\DB;

/**
 * Lädt Leistungen aus der DB: erfasste Noten (Module nur aus der jüngsten Belegung) und optional geplante Prüfungen.
 * Für viele Lernende gebündelt, damit Dashboards mit wenigen Queries auskommen.
 */
final class NotenQuelle
{
    public function __construct(private readonly Rechenkern $kern = new Rechenkern) {}

    public function auswertung(int $lernenderId, bool $mitGeplanten = false): Auswertung
    {
        return $this->kern->auswerten($this->fuerLernenden($lernenderId, $mitGeplanten), Konfiguration::ausDb());
    }

    /**
     * @param  list<int>  $lernenderIds
     * @return array<int, Auswertung>
     */
    public function auswertungen(array $lernenderIds): array
    {
        $k = Konfiguration::ausDb();
        $leistungen = $this->fuerLernende($lernenderIds);

        $out = [];
        foreach ($lernenderIds as $id) {
            $out[$id] = $this->kern->auswerten($leistungen[$id] ?? [], $k);
        }

        return $out;
    }

    /** @return list<Leistung> */
    public function fuerLernenden(int $lernenderId, bool $mitGeplanten = false): array
    {
        return $this->fuerLernende([$lernenderId], $mitGeplanten)[$lernenderId] ?? [];
    }

    /**
     * @param  list<int>  $lernenderIds
     * @return array<int, list<Leistung>>
     */
    public function fuerLernende(array $lernenderIds, bool $mitGeplanten = false): array
    {
        if ($lernenderIds === []) {
            return [];
        }

        $noten = DB::table('noten as n')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->whereIn('n.lernender_id', $lernenderIds)
            ->whereNull('n.geloescht_am')
            ->orderBy('n.pruefungsdatum')
            ->orderBy('n.note_id')
            ->get(['n.note_id', 'n.lernender_id', 'n.kategorie_id', 'n.fach_id', 'mb.modul_id', 'mb.modul_belegung_id',
                'mb.start_datum as belegung_start', 'n.semester_id', 'n.pruefungsdatum', 'n.note_wert', 'n.gewichtung_prozent', 'n.titel']);

        // jüngste Belegung je Lernender und Modul; ältere (wiederholte) zählen nicht
        $aktuelleBelegung = [];
        foreach ($noten as $n) {
            if ($n->modul_id === null) {
                continue;
            }
            $schluessel = $n->lernender_id.'-'.$n->modul_id;
            $bisher = $aktuelleBelegung[$schluessel] ?? null;
            if ($bisher === null || [$n->belegung_start, $n->modul_belegung_id] > $bisher) {
                $aktuelleBelegung[$schluessel] = [$n->belegung_start, $n->modul_belegung_id];
            }
        }

        $out = [];
        foreach ($noten as $n) {
            if ($n->modul_id !== null && $aktuelleBelegung[$n->lernender_id.'-'.$n->modul_id][1] !== $n->modul_belegung_id) {
                continue;
            }
            $out[(int) $n->lernender_id][] = new Leistung(
                kategorieId: (int) $n->kategorie_id,
                fachId: $n->fach_id !== null ? (int) $n->fach_id : null,
                modulId: $n->modul_id !== null ? (int) $n->modul_id : null,
                semesterId: (int) $n->semester_id,
                datum: (string) $n->pruefungsdatum,
                wert: (float) $n->note_wert,
                gewicht: $n->gewichtung_prozent !== null ? (float) $n->gewichtung_prozent : 100.0,
                quelle: Leistung::NOTE,
                id: (int) $n->note_id,
                titel: $n->titel,
            );
        }

        if ($mitGeplanten) {
            foreach ($this->geplante($lernenderIds) as $lernenderId => $liste) {
                array_push($out[$lernenderId] ??= [], ...$liste);
            }
        }

        return $out;
    }

    /**
     * Geplante Prüfungen als unbekannte Leistungen; Kategorie aus Fach bzw. Lernort des Moduls im Beruf.
     *
     * @param  list<int>  $lernenderIds
     * @return array<int, list<Leistung>>
     */
    public function geplante(array $lernenderIds): array
    {
        $zeilen = DB::table('pruefungen as p')
            ->join('lernende as l', 'l.lernender_id', '=', 'p.lernender_id')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'p.fach_id')
            ->leftJoin('lehrberuf_module as lbm', fn ($j) => $j->on('lbm.modul_id', '=', 'p.modul_id')->on('lbm.lehrberuf_id', '=', 'l.lehrberuf_id'))
            ->whereIn('p.lernender_id', $lernenderIds)
            ->orderBy('p.datum')
            ->get(['p.pruefung_id', 'p.lernender_id', 'p.fach_id', 'p.modul_id', 'p.titel', 'p.datum', 'p.gewichtung_prozent',
                DB::raw('COALESCE(f.kategorie_id, lbm.kategorie_id) as kategorie_id')]);

        $out = [];
        foreach ($zeilen as $p) {
            if ($p->kategorie_id === null) {
                continue;
            }
            $out[(int) $p->lernender_id][] = new Leistung(
                kategorieId: (int) $p->kategorie_id,
                fachId: $p->fach_id !== null ? (int) $p->fach_id : null,
                modulId: $p->modul_id !== null ? (int) $p->modul_id : null,
                semesterId: null,
                datum: (string) $p->datum,
                wert: null,
                gewicht: (float) $p->gewichtung_prozent,
                quelle: Leistung::GEPLANT,
                id: (int) $p->pruefung_id,
                titel: $p->titel,
            );
        }

        return $out;
    }
}
