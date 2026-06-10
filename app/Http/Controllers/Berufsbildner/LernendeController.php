<?php

declare(strict_types=1);

namespace App\Http\Controllers\Berufsbildner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LernendeController extends Controller
{
    public function __construct(
        private readonly \App\Services\Noten\NoteService $noteService
    ) {}

    /**
     * Liste der aktuell betreuten Lernenden mit Notenstats.
     *
     * Regeln:
     * - Nur Lernende, die aktuell betreut werden (betreuungen.gueltig_von/bis)
     * - Nur aktive Benutzer, nicht gelöscht
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $bb = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        $today = now()->toDateString();

        $lernende = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->where('bt.berufsbildner_id', $bb->berufsbildner_id)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('bt.gueltig_bis')
                  ->orWhere('bt.gueltig_bis', '>=', $today);
            })
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select([
                'l.lernender_id',
                'b.vorname',
                'b.nachname',
                'b.email',
                'l.lehrende',
            ])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        if ($lernende->isEmpty()) {
            return view('berufsbildner.lernende.index', [
                'lernende' => collect(),
                'stats'    => collect(),
            ]);
        }

        $ids = $lernende->pluck('lernender_id')->map(fn($v) => (int)$v)->all();

        // Letztes Eintrags-Datum je Lernender
        $lastEntries = DB::table('noten')
            ->whereIn('lernender_id', $ids)
            ->whereNull('geloescht_am')
            ->groupBy('lernender_id')
            ->select(['lernender_id', DB::raw('MAX(pruefungsdatum) as last_entry')])
            ->get()
            ->keyBy('lernender_id');

        // Semester-Durchschnitt je Lernender (aktuelles Semester, gewichtet)
        $currentSemAvg = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->whereIn('n.lernender_id', $ids)
            ->whereNull('n.geloescht_am')
            ->where('s.start_datum', '<=', $today)
            ->where('s.end_datum', '>=', $today)
            ->groupBy('n.lernender_id')
            ->select([
                'n.lernender_id',
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent, 100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent, 100)), 0), 2) as avg'),
                DB::raw('COUNT(*) as count'),
            ])
            ->get()
            ->keyBy('lernender_id');

        $bbBenutzerId = (int) $user->benutzer_id;

        $unreadCounts = DB::table('noten as n')
            ->leftJoin('noten_gesehen as ng', function ($j) use ($bbBenutzerId) {
                $j->on('ng.note_id', '=', 'n.note_id')
                  ->where('ng.viewer_benutzer_id', '=', $bbBenutzerId);
            })
            ->leftJoin('noten_kommentare as nk', function ($j) {
                $j->on('nk.note_id', '=', 'n.note_id');
            })
            ->whereIn('n.lernender_id', $ids)
            ->whereNull('n.geloescht_am')
            ->where(function ($q) {
                $q->whereNull('ng.gesehen_am')
                  ->orWhereColumn('n.erstellt_am', '>', 'ng.gesehen_am')
                  ->orWhereColumn('nk.erstellt_am', '>', 'ng.gesehen_am');
            })
            ->groupBy('n.lernender_id')
            ->select(['n.lernender_id', DB::raw('COUNT(DISTINCT n.note_id) as unread')])
            ->get()
            ->keyBy('lernender_id');

        $stats = $lernende->map(function ($l) use ($lastEntries, $currentSemAvg, $unreadCounts) {
            $lid       = (int) $l->lernender_id;
            $lastRow   = $lastEntries->get($lid);
            $avgRow    = $currentSemAvg->get($lid);
            $lastEntry = $lastRow ? Carbon::parse($lastRow->last_entry) : null;
            $daysSince = $lastEntry ? (int) $lastEntry->diffInDays(now()) : null;
            $semAvg    = $avgRow ? round((float) $avgRow->avg, 2) : null;
            $unread    = (int) ($unreadCounts->get($lid)?->unread ?? 0);

            return (object) [
                'lernender_id' => $lid,
                'lastEntry'    => $lastEntry,
                'daysSince'    => $daysSince,
                'semAvg'       => $semAvg,
                'unread'       => $unread,
                'warningGelb'  => $daysSince === null || $daysSince > 30,
                'warningRot'   => $semAvg !== null && $semAvg < 4.0,
            ];
        })->keyBy('lernender_id');

        return view('berufsbildner.lernende.index', compact('lernende', 'stats'));
    }

    /**
     * Read-only Profil-Ansicht für Berufsbildner.
     * Zeigt Stammdaten + Semester-Statistiken. Keine Edit-Aktionen.
     */
    public function show(Request $request, int $lernender_id)
    {
        $bb = $request->user()?->berufsbildner;
        if (!$bb) {
            abort(403);
        }

        $today = now()->toDateString();

        // Authorization: BB darf nur aktuell betreute Lernende sehen
        $betreut = DB::table('betreuungen')
            ->where('berufsbildner_id', $bb->berufsbildner_id)
            ->where('lernender_id', $lernender_id)
            ->where('gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today))
            ->exists();

        if (!$betreut) {
            abort(403, 'Sie betreuen diesen Lernenden nicht.');
        }

        $profil = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.lernender_id', $lernender_id)
            ->whereNull('l.geloescht_am')
            ->select([
                'l.lernender_id', 'l.lehrbeginn', 'l.lehrende',
                'b.vorname', 'b.nachname', 'b.email',
                'lb.name as lehrberuf',
            ])
            ->first();

        if (!$profil) {
            abort(404);
        }

        // Tracks (BMS / ABU)
        $tracks = DB::table('lernender_tracks')
            ->where('lernender_id', $lernender_id)
            ->select(['track_typ', 'start_datum', 'end_datum'])
            ->orderBy('start_datum')
            ->get();

        // Semester-Statistiken
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

        // Letzter Eintrag und Gesamt-Ø
        $lastEntry = DB::table('noten')
            ->where('lernender_id', $lernender_id)
            ->whereNull('geloescht_am')
            ->max('pruefungsdatum');

        $globalAvg = DB::table('noten')
            ->where('lernender_id', $lernender_id)
            ->whereNull('geloescht_am')
            ->selectRaw('ROUND(SUM(note_wert * COALESCE(gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(gewichtung_prozent,100)),0),2) as avg')
            ->value('avg');

        $noteCount = DB::table('noten')
            ->where('lernender_id', $lernender_id)
            ->whereNull('geloescht_am')
            ->count();

        return view('berufsbildner.lernende.show', [
            'profil'       => $profil,
            'tracks'       => $tracks,
            'semStats'     => $semStats,
            'lastEntry'    => $lastEntry ? Carbon::parse($lastEntry) : null,
            'globalAvg'    => $globalAvg !== null ? (float) $globalAvg : null,
            'noteCount'    => $noteCount,
            'notenVerlauf' => $this->noteService->notenVerlauf($lernender_id),
        ]);
    }

    /**
     * BB aktualisiert begrenzte Profilfelder eines betreuten Lernenden.
     *
     * Erlaubt: lehrbeginn, lehrende.
     * Nicht erlaubt: E-Mail, Passwort, Rolle, Benutzername (nur Admin).
     * Hinweis: Ein Bemerkungs-/Notizfeld existiert im Schema der Tabelle
     * `lernende` nicht und kann ohne ALTER-Rechte nicht ergänzt werden.
     */
    public function update(Request $request, int $lernender_id)
    {
        $bb = $request->user()?->berufsbildner;
        if (!$bb) {
            abort(403);
        }

        // Authorization: BB darf nur aktuell betreute Lernende bearbeiten
        $today = now()->toDateString();
        $betreut = DB::table('betreuungen')
            ->where('berufsbildner_id', $bb->berufsbildner_id)
            ->where('lernender_id', $lernender_id)
            ->where('gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today))
            ->exists();

        abort_if(!$betreut, 403, 'Sie betreuen diesen Lernenden nicht.');

        $validated = $request->validate([
            'lehrbeginn' => ['required', 'date'],
            'lehrende'   => ['nullable', 'date', 'after_or_equal:lehrbeginn'],
        ]);

        DB::table('lernende')
            ->where('lernender_id', $lernender_id)
            ->whereNull('geloescht_am')
            ->update([
                'lehrbeginn'      => $validated['lehrbeginn'],
                'lehrende'        => $validated['lehrende'] ?? null,
                'aktualisiert_am' => now(),
            ]);

        return redirect()
            ->route('berufsbildner.lernende.show', ['lernender_id' => $lernender_id])
            ->with('status', 'Profil aktualisiert.');
    }
}
