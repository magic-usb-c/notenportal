<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LernendeController extends Controller
{
    /**
     * Admin: Liste aller aktiven Lernenden mit Notenzahl, letztem Datum und Ø.
     */
    public function index(Request $request)
    {
        $today        = now()->toDateString();
        $suche        = $request->input('suche', '');
        $warnung      = $request->input('warnung', '');    // 'keine_noten' | 'tief_avg' | ''
        $inaktive     = $request->input('inaktive', '0'); // '1' = auch inaktive zeigen
        $bbFilterId   = $request->input('berufsbildner_id', ''); // BB-Filter

        $q = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'b.email', 'b.aktiv', 'l.lehrende'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname');

        if ($inaktive !== '1') {
            $q->where('b.aktiv', 1);
        }

        if ($suche !== '') {
            $like = '%' . $suche . '%';
            $q->where(fn($w) => $w
                ->where('b.vorname', 'like', $like)
                ->orWhere('b.nachname', 'like', $like)
                ->orWhere('b.email', 'like', $like)
            );
        }

        if ($bbFilterId !== '') {
            $q->whereIn('l.lernender_id', function ($sub) use ($bbFilterId, $today) {
                $sub->select('lernender_id')
                    ->from('betreuungen')
                    ->where('berufsbildner_id', (int) $bbFilterId)
                    ->where('gueltig_von', '<=', $today)
                    ->where(fn($w) => $w->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today));
            });
        }

        // Für BB-Dropdown: alle aktiven Berufsbildner
        $berufsbildnerListe = DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bb.berufsbildner_id', 'b.vorname', 'b.nachname'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        $lernende = $q->get();

        if ($lernende->isEmpty()) {
            return view('admin.lernende.index', [
                'lernende' => collect(), 'stats' => collect(),
                'suche' => $suche, 'warnung' => $warnung, 'inaktive' => $inaktive,
                'bbFilterId' => $bbFilterId, 'berufsbildnerListe' => $berufsbildnerListe,
            ]);
        }

        $ids = $lernende->pluck('lernender_id')->map(fn($v) => (int)$v)->all();

        // Noten-Stats: Anzahl, letztes Datum, gewichteter Schnitt
        $notenStats = DB::table('noten')
            ->whereIn('lernender_id', $ids)
            ->whereNull('geloescht_am')
            ->groupBy('lernender_id')
            ->select([
                'lernender_id',
                DB::raw('COUNT(*) as noten_count'),
                DB::raw('MAX(pruefungsdatum) as last_note'),
                DB::raw('ROUND(SUM(note_wert * COALESCE(gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(gewichtung_prozent,100)),0),2) as avg_all'),
            ])
            ->get()
            ->keyBy('lernender_id');

        // Aktueller Berufsbildner je Lernender
        $betreuer = DB::table('betreuungen as bt')
            ->join('berufsbildner as bb', 'bb.berufsbildner_id', '=', 'bt.berufsbildner_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereIn('bt.lernender_id', $ids)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->select(['bt.lernender_id', 'b.vorname', 'b.nachname'])
            ->get()
            ->keyBy('lernender_id');

        $cutoff = now()->subDays(30)->toDateString();
        $stats = collect($ids)->mapWithKeys(fn($id) => [$id => (object)[
            'noten_count' => (int) ($notenStats->get($id)?->noten_count ?? 0),
            'last_note'   => $notenStats->get($id)?->last_note,
            'avg_all'     => $notenStats->get($id)?->avg_all,
            'betreuer'    => $betreuer->get($id),
        ]]);

        // Warnungs-Filter (post-query, da stats abhängig)
        if ($warnung === 'keine_noten') {
            $lernende = $lernende->filter(function ($l) use ($stats, $cutoff) {
                $s = $stats->get((int) $l->lernender_id);
                return !$s || !$s->last_note || $s->last_note < $cutoff;
            });
        } elseif ($warnung === 'tief_avg') {
            $lernende = $lernende->filter(function ($l) use ($stats) {
                $s = $stats->get((int) $l->lernender_id);
                return $s && $s->avg_all !== null && (float) $s->avg_all < 4.0;
            });
        } elseif ($warnung === 'ohne_betreuung') {
            $lernende = $lernende->filter(function ($l) use ($stats) {
                $s = $stats->get((int) $l->lernender_id);
                return !$s?->betreuer;
            });
        }

        return view('admin.lernende.index', compact('lernende', 'stats', 'suche', 'warnung', 'inaktive', 'bbFilterId', 'berufsbildnerListe'));
    }

    /**
     * Admin: Detailseite eines Lernenden (Profil, Betreuung, Tracks, Notenstats).
     */
    public function show(Request $request, int $lernender_id)
    {
        $lernender = $this->lernenderOr404($lernender_id);
        $today     = now()->toDateString();

        // Erweitertes Profil
        $profil = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.lernender_id', $lernender_id)
            ->select(['l.*', 'b.vorname', 'b.nachname', 'b.email', 'lb.name as lehrberuf'])
            ->first();

        // Aktueller Berufsbildner
        $aktuellerBB = DB::table('betreuungen as bt')
            ->join('berufsbildner as bb', 'bb.berufsbildner_id', '=', 'bt.berufsbildner_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->where('bt.lernender_id', $lernender_id)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->select(['b.vorname', 'b.nachname', 'b.email'])
            ->first();

        // Semester-Statistiken (pro Semester: Anzahl, gewichteter Schnitt)
        $semStats = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->groupBy('n.semester_id', 's.bezeichnung', 's.sortierung')
            ->select([
                'n.semester_id',
                's.bezeichnung as sem_label',
                's.sortierung',
                DB::raw('COUNT(*) as count'),
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0),2) as avg'),
            ])
            ->orderBy('s.sortierung')
            ->get();

        // Letzte 5 Noten
        $letzteNoten = DB::table('noten as n')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->orderByDesc('n.pruefungsdatum')
            ->orderByDesc('n.note_id')
            ->limit(5)
            ->select(['n.note_id','n.note_wert','n.pruefungsdatum','n.titel','f.name as fach_name','m.modul_nummer','m.titel as modul_titel'])
            ->get();

        return view('admin.lernende.show', compact(
            'lernender', 'profil', 'aktuellerBB', 'semStats', 'letzteNoten', 'lernender_id'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Lernenden-Profil bearbeiten (Lehrberuf, Lehrbeginn, Lehrende)
    |--------------------------------------------------------------------------
    */

    public function editProfil(int $lernender_id)
    {
        $lernender = $this->lernenderOr404($lernender_id);

        $profil = DB::table('lernende')
            ->where('lernender_id', $lernender_id)
            ->select(['lehrberuf_id', 'lehrbeginn', 'lehrende'])
            ->first();

        $lehrberufe = DB::table('lehrberufe')
            ->where('aktiv', 1)
            ->orderBy('name')
            ->get();

        return view('admin.lernende.profil_edit', compact('lernender', 'profil', 'lehrberufe', 'lernender_id'));
    }

    public function updateProfil(Request $request, int $lernender_id): RedirectResponse
    {
        $this->lernenderOr404($lernender_id);

        $validated = $request->validate([
            'lehrberuf_id' => ['nullable', 'integer', 'exists:lehrberufe,lehrberuf_id'],
            'lehrbeginn'   => ['nullable', 'date'],
            'lehrende'     => ['nullable', 'date', 'after_or_equal:lehrbeginn'],
        ]);

        DB::table('lernende')
            ->where('lernender_id', $lernender_id)
            ->update([
                'lehrberuf_id'   => $validated['lehrberuf_id'] ?? null,
                'lehrbeginn'     => $validated['lehrbeginn']   ?? null,
                'lehrende'       => $validated['lehrende']     ?? null,
                'aktualisiert_am' => now(),
            ]);

        return redirect()
            ->route('admin.lernende.show', $lernender_id)
            ->with('status', 'Lernenden-Profil aktualisiert.');
    }

    /*
    |--------------------------------------------------------------------------
    | Betreuungen
    |--------------------------------------------------------------------------
    */

    public function betreuung(Request $request, int $lernender_id)
    {
        $lernender = $this->lernenderOr404($lernender_id);

        // Aktive und abgeschlossene Betreuungen
        $betreuungen = DB::table('betreuungen as bt')
            ->join('berufsbildner as bb', 'bb.berufsbildner_id', '=', 'bt.berufsbildner_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->where('bt.lernender_id', $lernender_id)
            ->select([
                'bt.betreuung_id',
                'bt.gueltig_von',
                'bt.gueltig_bis',
                'b.vorname',
                'b.nachname',
                'b.email',
            ])
            ->orderByDesc('bt.gueltig_von')
            ->get();

        // Alle aktiven Berufsbildner für Auswahl-Dropdown
        $berufsbildner = DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bb.berufsbildner_id', 'b.vorname', 'b.nachname', 'b.email'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        return view('admin.lernende.betreuung', compact('lernender', 'betreuungen', 'berufsbildner', 'lernender_id'));
    }

    public function betreuungStore(Request $request, int $lernender_id): RedirectResponse
    {
        $this->lernenderOr404($lernender_id);

        $validated = $request->validate([
            'berufsbildner_id' => ['required', 'integer', 'exists:berufsbildner,berufsbildner_id'],
            'gueltig_von'      => ['required', 'date'],
        ]);

        DB::table('betreuungen')->insert([
            'lernender_id'    => $lernender_id,
            'berufsbildner_id' => $validated['berufsbildner_id'],
            'gueltig_von'     => $validated['gueltig_von'],
            'gueltig_bis'     => null,
        ]);

        return redirect()
            ->route('admin.lernende.betreuung', $lernender_id)
            ->with('status', 'Betreuung eingetragen.');
    }

    public function betreuungEnd(Request $request, int $betreuung_id): RedirectResponse
    {
        $bt = DB::table('betreuungen')->where('betreuung_id', $betreuung_id)->first();
        abort_if(!$bt, 404);

        DB::table('betreuungen')
            ->where('betreuung_id', $betreuung_id)
            ->whereNull('gueltig_bis')  // nur aktive Betreuungen beenden
            ->update(['gueltig_bis' => now()->toDateString()]);

        return redirect()
            ->route('admin.lernende.betreuung', $bt->lernender_id)
            ->with('status', 'Betreuung beendet.');
    }

    /*
    |--------------------------------------------------------------------------
    | Tracks (BMS / ABU)
    |--------------------------------------------------------------------------
    */

    public function tracks(Request $request, int $lernender_id)
    {
        $lernender = $this->lernenderOr404($lernender_id);

        $tracks = DB::table('lernender_tracks as lt')
            ->leftJoin('semester as ss', 'ss.semester_id', '=', 'lt.start_semester_id')
            ->leftJoin('semester as es', 'es.semester_id', '=', 'lt.end_semester_id')
            ->where('lt.lernender_id', $lernender_id)
            ->select([
                'lt.lernender_track_id',
                'lt.track_typ',
                'lt.start_datum',
                'lt.end_datum',
                'ss.bezeichnung as start_semester',
                'es.bezeichnung as end_semester',
            ])
            ->orderByDesc('lt.start_datum')
            ->get();

        $semester = DB::table('semester')->orderBy('sortierung')->get();

        return view('admin.lernende.tracks', compact('lernender', 'tracks', 'semester', 'lernender_id'));
    }

    public function trackStore(Request $request, int $lernender_id): RedirectResponse
    {
        $this->lernenderOr404($lernender_id);

        $validated = $request->validate([
            'track_typ'         => ['required', 'in:BMS,ABU'],
            'start_datum'       => ['required', 'date'],
            'start_semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
        ]);

        DB::table('lernender_tracks')->insert([
            'lernender_id'      => $lernender_id,
            'track_typ'         => $validated['track_typ'],
            'start_datum'       => $validated['start_datum'],
            'end_datum'         => null,
            'start_semester_id' => $validated['start_semester_id'],
            'end_semester_id'   => null,
        ]);

        return redirect()
            ->route('admin.lernende.tracks', $lernender_id)
            ->with('status', 'Track hinzugefügt.');
    }

    public function trackEnd(Request $request, int $track_id): RedirectResponse
    {
        $track = DB::table('lernender_tracks')->where('lernender_track_id', $track_id)->first();
        abort_if(!$track, 404);

        $validated = $request->validate([
            'end_semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
        ]);

        DB::table('lernender_tracks')
            ->where('lernender_track_id', $track_id)
            ->whereNull('end_datum')
            ->update([
                'end_datum'        => now()->toDateString(),
                'end_semester_id'  => $validated['end_semester_id'],
            ]);

        return redirect()
            ->route('admin.lernende.tracks', $track->lernender_id)
            ->with('status', 'Track beendet.');
    }

    // ---------------------------------------------------------------------------

    private function lernenderOr404(int $lernender_id): object
    {
        $lernender = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->where('l.lernender_id', $lernender_id)
            ->whereNull('l.geloescht_am')
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'b.email'])
            ->first();

        abort_if(!$lernender, 404);
        return $lernender;
    }
}
