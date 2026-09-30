<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use App\Services\Auswertung\Leistung;
use Illuminate\Support\Facades\DB;

/**
 * Lädt aktive Notenbäume aus der DB und ordnet sie Lernenden zu: der Baum ihres Lehrberufs (QV) und
 * die Bäume ihrer laufenden Bildungsgänge (z. B. Berufsmaturität über den Track «BMS»).
 */
final class BaumLader
{
    /** @var array<int, array{baum: Baum, lehrberuf_id: ?int, track_typ: ?string}>|null */
    private static ?array $aktive = null;

    /** Nach Änderungen an Bäumen im selben Request (Bearbeitung, Import, Tests). */
    public static function vergessen(): void
    {
        self::$aktive = null;
    }

    /** @return array<int, array{baum: Baum, lehrberuf_id: ?int, track_typ: ?string}> */
    public function aktive(): array
    {
        return self::$aktive ??= $this->laden(DB::table('notenbaeume')->where('aktiv', true)->orderBy('baum_id')->get()->all());
    }

    /** Einzelner Baum unabhängig vom Aktiv-Schalter (Vorschau in der Verwaltung). */
    public function baum(int $baumId): ?Baum
    {
        $zeile = DB::table('notenbaeume')->where('baum_id', $baumId)->first();

        return $zeile ? ($this->laden([$zeile])[$baumId]['baum'] ?? null) : null;
    }

    /**
     * @param  list<object>  $zeilen
     * @return array<int, array{baum: Baum, lehrberuf_id: ?int, track_typ: ?string}>
     */
    private function laden(array $zeilen): array
    {
        if ($zeilen === []) {
            return [];
        }
        $ids = array_map(fn ($z) => (int) $z->baum_id, $zeilen);

        $knoten = DB::table('notenbaum_knoten')->whereIn('baum_id', $ids)->orderBy('sortierung')->orderBy('knoten_id')->get();
        $faecher = DB::table('notenbaum_knoten_faecher')->whereIn('knoten_id', $knoten->pluck('knoten_id'))->get()
            ->groupBy('knoten_id')->map(fn ($g) => $g->pluck('fach_id')->map(fn ($id) => (int) $id)->all());

        $nachEltern = [];
        foreach ($knoten as $k) {
            $nachEltern[(int) $k->baum_id][$k->eltern_id !== null ? (int) $k->eltern_id : 0][] = $k;
        }

        $out = [];
        foreach ($zeilen as $z) {
            $wurzeln = $nachEltern[(int) $z->baum_id][0] ?? [];
            if (count($wurzeln) !== 1) {
                continue; // leerer oder kaputter Baum rechnet nicht, statt falsch zu rechnen
            }
            $baum = new Baum((int) $z->baum_id, (string) $z->name,
                $this->knoten($wurzeln[0], $nachEltern[(int) $z->baum_id], $faecher->all()),
                $z->bezug === Baum::BILDUNGSGANG ? Baum::BILDUNGSGANG : Baum::LEHRBERUF);
            $out[$baum->id] = ['baum' => $baum, 'lehrberuf_id' => $z->lehrberuf_id !== null ? (int) $z->lehrberuf_id : null,
                'track_typ' => $z->track_typ];
        }

        return $out;
    }

    /**
     * @param  array<int, list<object>>  $nachEltern
     * @param  array<int, list<int>>  $faecher
     */
    private function knoten(object $k, array $nachEltern, array $faecher): Knoten
    {
        $kinder = array_map(fn ($kind) => $this->knoten($kind, $nachEltern, $faecher), $nachEltern[(int) $k->knoten_id] ?? []);

        return new Knoten(
            id: (int) $k->knoten_id,
            code: (string) $k->code,
            name: (string) $k->name,
            typ: (string) $k->typ,
            gewicht: (float) $k->gewicht,
            rundung: $k->rundung !== null ? (float) $k->rundung : null,
            fallnote: $k->fallnote !== null ? (float) $k->fallnote : null,
            maxUngenuegend: $k->max_ungenuegend !== null ? (int) $k->max_ungenuegend : null,
            maxMinuspunkte: $k->max_minuspunkte !== null ? (float) $k->max_minuspunkte : null,
            zaehlt: (bool) $k->zaehlt,
            kategorieId: $k->kategorie_id !== null ? (int) $k->kategorie_id : null,
            elementtyp: (string) $k->elementtyp,
            faecher: $faecher[(int) $k->knoten_id] ?? [],
            kinder: $k->typ === Knoten::GRUPPE ? $kinder : [],
            entfaelltMitTrack: $k->entfaellt_mit_track ?? null,
        );
    }

    /**
     * Bäume je Lernender: zuerst der Baum des Lehrberufs, dann die Bäume laufender Bildungsgänge.
     *
     * @param  list<int>  $lernenderIds
     * @return array<int, list<Baum>>
     */
    public function fuerLernende(array $lernenderIds): array
    {
        $aktive = $this->aktive();
        if ($aktive === [] || $lernenderIds === []) {
            return [];
        }

        $lehrberufe = DB::table('lernende')->whereIn('lernender_id', $lernenderIds)->pluck('lehrberuf_id', 'lernender_id');
        $heute = now()->toDateString();
        $tracks = DB::table('lernender_tracks')->whereIn('lernender_id', $lernenderIds)
            ->where('start_datum', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('end_datum')->orWhere('end_datum', '>=', $heute))
            ->get(['lernender_id', 'track_typ'])->groupBy('lernender_id')
            ->map(fn ($g) => $g->pluck('track_typ')->unique()->values()->all());

        // Massgebend für «entfällt mit Track» ist der zuletzt begonnene Track: wer die BM abschliesst oder
        // noch besucht, hat BMS; wer aus der BM in den ABU-Unterricht wechselt, hat danach ABU.
        $letzter = DB::table('lernender_tracks')->whereIn('lernender_id', $lernenderIds)->where('start_datum', '<=', $heute)
            ->orderBy('start_datum')->orderBy('lernender_track_id')->get(['lernender_id', 'track_typ'])
            ->mapWithKeys(fn ($t) => [(int) $t->lernender_id => (string) $t->track_typ])->all();

        $out = [];
        foreach ($lernenderIds as $id) {
            $liste = [];
            foreach ($aktive as $a) {
                if ($a['baum']->bezug === Baum::LEHRBERUF && $a['lehrberuf_id'] !== null && $a['lehrberuf_id'] === (int) ($lehrberufe[$id] ?? 0)) {
                    $liste[] = $a['baum']->fuerTrack($letzter[$id] ?? null);
                    break; // ein Baum je Lehrberuf; weitere aktive werden ignoriert
                }
            }
            foreach ($aktive as $a) {
                if ($a['baum']->bezug === Baum::BILDUNGSGANG && in_array($a['track_typ'], $tracks[$id] ?? [], true)) {
                    $liste[] = $a['baum'];
                }
            }
            if ($liste !== []) {
                $out[$id] = $liste;
            }
        }

        return $out;
    }

    /**
     * Von Hand erfasste Positionen als Leistungen.
     *
     * @param  list<int>  $lernenderIds
     * @return array<int, list<Leistung>>
     */
    public function positionen(array $lernenderIds): array
    {
        if ($lernenderIds === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('notenbaum_positionen')->whereIn('lernender_id', $lernenderIds)->orderBy('position_id')->get() as $p) {
            $out[(int) $p->lernender_id][] = new Leistung(0, null, null, null, $p->datum, (float) $p->note_wert,
                id: (int) $p->position_id, knotenId: (int) $p->knoten_id);
        }

        return $out;
    }
}
