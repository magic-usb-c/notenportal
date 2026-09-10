<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Semester;
use App\Services\Noten\NoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService
    ) {}

    /** Lernender-Dashboard */
    public function lernender(Request $request)
    {
        $lernender = $request->user()?->lernender;

        if (! $lernender) {
            abort(403);
        }

        $lernenderId = (int) $lernender->lernender_id;
        $benutzerId = (int) $request->user()->benutzer_id;
        $today = now()->toDateString();

        // Aktuelles Semester
        $currentSemester = Semester::query()
            ->where('start_datum', '<=', $today)
            ->where('end_datum', '>=', $today)
            ->first();

        // Gesamtzahl Noten
        $noteCount = Note::query()
            ->where('lernender_id', $lernenderId)
            ->count();

        // Durchschnitt aktuelles Semester (gewichtet)
        $currentAvg = null;
        if ($currentSemester) {
            $row = DB::table('noten')
                ->where('lernender_id', $lernenderId)
                ->where('semester_id', $currentSemester->semester_id)
                ->whereNull('geloescht_am')
                ->selectRaw('SUM(note_wert * COALESCE(gewichtung_prozent, 100)) / NULLIF(SUM(COALESCE(gewichtung_prozent, 100)), 0) as avg')
                ->first();
            $currentAvg = $row?->avg !== null ? round((float) $row->avg, 2) : null;
        }

        // Gesamtdurchschnitt über alle Semester (gewichtet)
        $globalRow = DB::table('noten')
            ->where('lernender_id', $lernenderId)
            ->whereNull('geloescht_am')
            ->selectRaw('SUM(note_wert * COALESCE(gewichtung_prozent, 100)) / NULLIF(SUM(COALESCE(gewichtung_prozent, 100)), 0) as avg')
            ->first();
        $globalAvg = $globalRow?->avg !== null ? round((float) $globalRow->avg, 2) : null;

        // Letzte 3 Noten (Preview)
        $letzteDreiNoten = DB::table('noten as n')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->orderByDesc('n.pruefungsdatum')
            ->orderByDesc('n.note_id')
            ->limit(3)
            ->select([
                'n.note_id', 'n.pruefungsdatum', 'n.titel', 'n.note_wert',
                'f.name as fach_name',
                'm.modul_nummer', 'm.titel as modul_titel',
            ])
            ->get();

        // Badge: Anzahl Noten mit ungelesenen BB-Kommentaren
        $ungeleseneKommentarNoten = DB::table('noten as n')
            ->join('noten_kommentare as nk', 'nk.note_id', '=', 'n.note_id')
            ->leftJoin('noten_gesehen as ng', function ($j) use ($benutzerId) {
                $j->on('ng.note_id', '=', 'n.note_id')
                    ->where('ng.viewer_benutzer_id', '=', $benutzerId);
            })
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->where('nk.autor_benutzer_id', '!=', $benutzerId)
            ->where(function ($q) {
                $q->whereNull('ng.gesehen_am')
                    ->orWhereColumn('nk.erstellt_am', '>', 'ng.gesehen_am');
            })
            ->distinct()
            ->count('n.note_id');

        // Lehrausbildungs-Profil für Restlaufzeit-Anzeige
        $lehrProfil = DB::table('lernende as l')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.lernender_id', $lernenderId)
            ->select(['l.lehrbeginn', 'l.lehrende', 'lb.name as lehrberuf_name'])
            ->first();

        // Notenverlauf: letzte 20 Noten chronologisch (für Liniendiagramm)
        $notenVerlauf = $this->noteService->notenVerlauf($lernenderId);

        return view('dashboards.lernender', compact(
            'lernender',
            'currentSemester',
            'noteCount',
            'currentAvg',
            'globalAvg',
            'letzteDreiNoten',
            'ungeleseneKommentarNoten',
            'lehrProfil',
            'notenVerlauf',
        ));
    }

    /** Berufsbildner-Dashboard */
    public function berufsbildner(Request $request)
    {
        $bb = $request->user()?->berufsbildner;

        if (! $bb) {
            abort(403);
        }

        $today = now()->toDateString();
        $bbId = (int) $bb->berufsbildner_id;

        // Betreute Lernende (inkl. Lehrberuf-Name für Card-Darstellung)
        $lernende = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('bt.berufsbildner_id', $bbId)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn ($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'b.email', 'lb.name as lehrberuf'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        if ($lernende->isEmpty()) {
            return view('dashboards.berufsbildner', [
                'lernende' => collect(),
                'stats' => collect(),
            ]);
        }

        $lernenderIds = $lernende->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();

        // Letztes Eintrags-Datum je Lernender
        $lastEntries = DB::table('noten')
            ->whereIn('lernender_id', $lernenderIds)
            ->whereNull('geloescht_am')
            ->groupBy('lernender_id')
            ->select(['lernender_id', DB::raw('MAX(pruefungsdatum) as last_entry')])
            ->get()
            ->keyBy('lernender_id');

        // Aktueller Semester-Durchschnitt je Lernender (gewichtet)
        $currentSemAvg = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->whereIn('n.lernender_id', $lernenderIds)
            ->whereNull('n.geloescht_am')
            ->where('s.start_datum', '<=', $today)
            ->where('s.end_datum', '>=', $today)
            ->groupBy('n.lernender_id')
            ->select([
                'n.lernender_id',
                DB::raw('SUM(n.note_wert * COALESCE(n.gewichtung_prozent, 100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent, 100)), 0) as avg'),
                DB::raw('COUNT(*) as count'),
            ])
            ->get()
            ->keyBy('lernender_id');

        $bbBenutzerId = (int) $request->user()->benutzer_id;

        // Ungelesene Noten pro Lernender (neue Note oder neuer Kommentar seit letztem gesehen_am)
        $unreadCounts = DB::table('noten as n')
            ->leftJoin('noten_gesehen as ng', function ($j) use ($bbBenutzerId) {
                $j->on('ng.note_id', '=', 'n.note_id')
                    ->where('ng.viewer_benutzer_id', '=', $bbBenutzerId);
            })
            ->leftJoin('noten_kommentare as nk', function ($j) {
                $j->on('nk.note_id', '=', 'n.note_id');
            })
            ->whereIn('n.lernender_id', $lernenderIds)
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

        // Stats pro Lernender berechnen
        $stats = $lernende->map(function ($l) use ($lastEntries, $currentSemAvg, $unreadCounts) {
            $lid = (int) $l->lernender_id;
            $lastRow = $lastEntries->get($lid);
            $avgRow = $currentSemAvg->get($lid);

            $lastEntry = $lastRow ? Carbon::parse($lastRow->last_entry) : null;
            $daysSince = $lastEntry ? (int) $lastEntry->diffInDays(now()) : null;
            $semAvg = $avgRow ? round((float) $avgRow->avg, 2) : null;
            $semCount = $avgRow ? (int) $avgRow->count : 0;
            $unread = (int) ($unreadCounts->get($lid)?->unread ?? 0);

            $warningGelb = $daysSince === null || $daysSince > 30;
            $warningRot = $semAvg !== null && $semAvg < 4.0;

            return (object) [
                'lernender_id' => $lid,
                'lastEntry' => $lastEntry,
                'daysSince' => $daysSince,
                'semAvg' => $semAvg,
                'semCount' => $semCount,
                'unread' => $unread,
                'warningGelb' => $warningGelb,
                'warningRot' => $warningRot,
            ];
        })->keyBy('lernender_id');

        // Lernende mit Lehrende in den nächsten 60 Tagen
        $lehrEndeBaldIds = $lernende->filter(fn ($l) => isset($l->lernende_lehrende))->pluck('lernender_id');

        // Wir holen die Lehrende-Daten für alle betreuten Lernenden
        $lernendeProfile = DB::table('lernende')
            ->whereIn('lernender_id', $lernenderIds)
            ->whereNotNull('lehrende')
            ->where('lehrende', '>=', $today)
            ->where('lehrende', '<=', now()->addDays(60)->toDateString())
            ->select(['lernender_id', 'lehrende'])
            ->get()
            ->keyBy('lernender_id');

        return view('dashboards.berufsbildner', compact('lernende', 'stats', 'lernendeProfile'));
    }

    /** Admin-Dashboard */
    public function admin(Request $request)
    {
        $today = now()->toDateString();

        $lernendCount = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->count();
        $berufsbildnerCount = DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->count();
        $noteCount = Note::query()->count();

        // Noten im aktuellen Semester
        $currentSemester = Semester::query()
            ->where('start_datum', '<=', $today)
            ->where('end_datum', '>=', $today)
            ->first();

        $notesThisSemester = $currentSemester
            ? Note::query()->where('semester_id', $currentSemester->semester_id)->count()
            : 0;

        // Letzte 5 Noten (für Activity-Feed)
        $letzteNoten = DB::table('noten as n')
            ->join('lernende as l', 'l.lernender_id', '=', 'n.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereNull('n.geloescht_am')
            ->orderByDesc('n.erstellt_am')
            ->limit(5)
            ->select(['n.note_id', 'n.note_wert', 'n.pruefungsdatum', 'n.erstellt_am', 'b.vorname', 'b.nachname', 'l.lernender_id'])
            ->get();

        // Benutzer ohne Noten im letzten Monat (Warnung)
        $lernendeMitLetzterNote = DB::table('noten as n')
            ->whereNull('n.geloescht_am')
            ->groupBy('n.lernender_id')
            ->select(['n.lernender_id', DB::raw('MAX(n.pruefungsdatum) as last_entry')])
            ->get()
            ->keyBy('lernender_id');

        // Lernende ohne Noteneintrag in den letzten 30 Tagen (Warnung auf Dashboard)
        $cutoff = now()->subDays(30)->toDateString();
        $lernendeOhneNoten = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin(DB::raw(
                '(SELECT lernender_id, MAX(pruefungsdatum) as last_entry
                  FROM noten WHERE geloescht_am IS NULL
                  GROUP BY lernender_id) as nn'
            ), 'nn.lernender_id', '=', 'l.lernender_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->where(fn ($q) => $q->whereNull('nn.last_entry')->orWhere('nn.last_entry', '<', $cutoff))
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'nn.last_entry'])
            ->orderBy('b.nachname')
            ->limit(10)
            ->get();

        // Lernende mit Lehrende in den nächsten 60 Tagen
        $lehrEndeBald = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->whereNotNull('l.lehrende')
            ->where('l.lehrende', '>=', $today)
            ->where('l.lehrende', '<=', now()->addDays(60)->toDateString())
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'l.lehrende', 'lb.name as lehrberuf'])
            ->orderBy('l.lehrende')
            ->get();

        // Erfassungs-Aktivität: Noten pro Woche, letzte 8 Wochen (nach Erfassungsdatum)
        $wochenStart = now()->startOfWeek()->subWeeks(7);
        $aktivitaetRaw = DB::table('noten')
            ->whereNull('geloescht_am')
            ->where('erstellt_am', '>=', $wochenStart)
            ->selectRaw('YEARWEEK(erstellt_am, 3) as kw, COUNT(*) as count')
            ->groupBy('kw')
            ->pluck('count', 'kw');

        $aktivitaet = collect(range(0, 7))->map(function ($i) use ($wochenStart, $aktivitaetRaw) {
            $w = $wochenStart->copy()->addWeeks($i);

            return (object) [
                'label' => 'KW '.$w->isoWeek(),
                'count' => (int) ($aktivitaetRaw->get((int) $w->format('oW')) ?? 0),
            ];
        });

        return view('dashboards.admin', compact(
            'lernendCount',
            'berufsbildnerCount',
            'noteCount',
            'notesThisSemester',
            'currentSemester',
            'letzteNoten',
            'lernendeOhneNoten',
            'lehrEndeBald',
            'aktivitaet',
        ));
    }
}
