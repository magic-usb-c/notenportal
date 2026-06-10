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
        private readonly \App\Services\Noten\NoteService $noteService,
        private readonly \App\Services\Benutzer\LernendeErfassungService $lernendeErfassung
    ) {}

    /**
     * Prüft, ob der eingeloggte BB den Lernenden aktuell betreut. 403 sonst.
     */
    private function betreuungOrAbort(Request $request, int $lernender_id): object
    {
        $bb = $request->user()?->berufsbildner;
        abort_if(!$bb, 403);

        $today = now()->toDateString();
        $betreut = DB::table('betreuungen')
            ->where('berufsbildner_id', $bb->berufsbildner_id)
            ->where('lernender_id', $lernender_id)
            ->where('gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today))
            ->exists();

        abort_if(!$betreut, 403, 'Sie betreuen diesen Lernenden nicht.');

        return $bb;
    }

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

        // Tracks (BMS / ABU) inkl. IDs und Semester-Labels für Verwaltung
        $tracks = DB::table('lernender_tracks as t')
            ->leftJoin('semester as s1', 's1.semester_id', '=', 't.start_semester_id')
            ->leftJoin('semester as s2', 's2.semester_id', '=', 't.end_semester_id')
            ->where('t.lernender_id', $lernender_id)
            ->select([
                't.lernender_track_id', 't.track_typ', 't.start_datum', 't.end_datum',
                's1.bezeichnung as start_semester', 's2.bezeichnung as end_semester',
            ])
            ->orderBy('t.start_datum')
            ->get();

        // Semester-Liste für Track-Start/-Ende-Formulare
        $semesterListe = DB::table('semester')->orderBy('sortierung')->get();

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
            'profil'        => $profil,
            'tracks'        => $tracks,
            'semesterListe' => $semesterListe,
            'semStats'      => $semStats,
            'lastEntry'     => $lastEntry ? Carbon::parse($lastEntry) : null,
            'globalAvg'     => $globalAvg !== null ? (float) $globalAvg : null,
            'noteCount'     => $noteCount,
            'notenVerlauf'  => $this->noteService->notenVerlauf($lernender_id),
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

    /*
    |--------------------------------------------------------------------------
    | Neuen Lernenden erfassen (Betreuung wird automatisch dem BB zugewiesen)
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        abort_if(!$request->user()?->berufsbildner, 403);

        $lehrberufe = DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get();
        $semester   = DB::table('semester')->orderBy('sortierung')->get();

        return view('berufsbildner.lernende.create', compact('lehrberufe', 'semester'));
    }

    public function store(Request $request)
    {
        $bb = $request->user()?->berufsbildner;
        abort_if(!$bb, 403);

        $validated = $request->validate([
            'vorname'           => ['required', 'string', 'max:100'],
            'nachname'          => ['required', 'string', 'max:100'],
            'email'             => ['required', 'email', 'max:255', 'unique:benutzer,email'],
            'benutzername'      => ['required', 'string', 'max:50', 'unique:benutzer,benutzername', 'alpha_num'],
            'passwort'          => ['required', 'string', 'min:8', 'confirmed'],
            'lehrberuf_id'      => ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'],
            'lehrbeginn'        => ['required', 'date'],
            'lehrende'          => ['nullable', 'date', 'after_or_equal:lehrbeginn'],
            'track_typ'         => ['nullable', 'in:BMS,ABU'],
            'track_semester_id' => ['required_if:track_typ,BMS', 'required_if:track_typ,ABU',
                                    'nullable', 'integer', 'exists:semester,semester_id'],
        ]);

        // Betreuung automatisch dem eingeloggten BB zuweisen
        $lernenderId = $this->lernendeErfassung->erstellen($validated, (int) $bb->berufsbildner_id);

        return redirect()
            ->route('berufsbildner.lernende.show', ['lernender_id' => $lernenderId])
            ->with('status', 'Lernender angelegt und deiner Betreuung zugewiesen.');
    }

    /*
    |--------------------------------------------------------------------------
    | Tracks (BMS/ABU) betreuter Lernender verwalten
    |--------------------------------------------------------------------------
    */

    public function trackStore(Request $request, int $lernender_id)
    {
        $this->betreuungOrAbort($request, $lernender_id);

        $validated = $request->validate([
            'track_typ'         => ['required', 'in:BMS,ABU'],
            'start_datum'       => ['required', 'date'],
            'start_semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
        ]);

        // Kein zweiter offener Track desselben Typs
        $offenExistiert = DB::table('lernender_tracks')
            ->where('lernender_id', $lernender_id)
            ->where('track_typ', $validated['track_typ'])
            ->whereNull('end_datum')
            ->exists();

        if ($offenExistiert) {
            return back()->with('error', 'Es läuft bereits ein offener ' . $validated['track_typ'] . '-Track.');
        }

        DB::table('lernender_tracks')->insert([
            'lernender_id'      => $lernender_id,
            'track_typ'         => $validated['track_typ'],
            'start_datum'       => $validated['start_datum'],
            'end_datum'         => null,
            'start_semester_id' => $validated['start_semester_id'],
            'end_semester_id'   => null,
        ]);

        return redirect()
            ->route('berufsbildner.lernende.show', ['lernender_id' => $lernender_id])
            ->with('status', 'Track gestartet.');
    }

    public function trackEnd(Request $request, int $track_id)
    {
        $track = DB::table('lernender_tracks')->where('lernender_track_id', $track_id)->first();
        abort_if(!$track, 404);

        $this->betreuungOrAbort($request, (int) $track->lernender_id);

        $validated = $request->validate([
            'end_semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
        ]);

        // DB-Constraint: end_datum >= start_datum. Startet der Track erst in der
        // Zukunft, wird er per end = start "storniert" statt mit heutigem Datum.
        $endDatum = max(now()->toDateString(), (string) $track->start_datum);

        DB::table('lernender_tracks')
            ->where('lernender_track_id', $track_id)
            ->whereNull('end_datum')
            ->update([
                'end_datum'       => $endDatum,
                'end_semester_id' => $validated['end_semester_id'],
            ]);

        return redirect()
            ->route('berufsbildner.lernende.show', ['lernender_id' => $track->lernender_id])
            ->with('status', 'Track beendet.');
    }
}
