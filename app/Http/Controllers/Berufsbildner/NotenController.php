<?php

declare(strict_types=1);

namespace App\Http\Controllers\Berufsbildner;

use App\Http\Controllers\Controller;
use App\Models\Kategorie;
use App\Models\Note;
use App\Services\Noten\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotenController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService
    ) {}

    public function index(Request $request, int $lernender_id)
    {
        $user = $request->user();
        $bb   = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        $today = now()->toDateString();

        // 1) Switcher-Liste: aktuell betreute Lernende
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
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'b.email'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        $selectedLernender = $lernende->firstWhere('lernender_id', $lernender_id);
        if (!$selectedLernender) {
            abort(404);
        }

        // 2) Noten mit Kommentaren + gesehen-Status (gefiltert auf diesen BB)
        $bbBenutzerId = (int) $user->benutzer_id;

        $q = Note::query()
            ->with([
                'kategorie',
                'semester',
                'fach',
                'modulBelegung.modul',
                'gruppe',
                // Kommentare chronologisch für Thread-Anzeige
                'kommentare' => fn($q) => $q->with('autor')->orderBy('erstellt_am', 'asc'),
                // Nur der gesehen-Eintrag dieses BBs (max. 1 pro Note)
                'gesehen' => fn($q) => $q->where('viewer_benutzer_id', $bbBenutzerId),
            ])
            ->where('lernender_id', $lernender_id)
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id');

        if ($request->filled('kategorie_id')) {
            $q->where('kategorie_id', (int) $request->input('kategorie_id'));
        }
        if ($request->filled('semester_id')) {
            $q->where('semester_id', (int) $request->input('semester_id'));
        }

        $notes = $q->paginate(25)->withQueryString();

        $kategorien = Kategorie::query()->orderBy('sortierung')->get();

        // Nur Semester während der Lehrzeit dieses Lernenden
        $semester = $this->noteService->semestersForLernender($lernender_id);

        // Kurzprofil: Lehrberuf + Lehrbeginn für Übersicht
        $lernenderProfil = DB::table('lernende as l')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.lernender_id', $lernender_id)
            ->select(['l.lehrbeginn', 'l.lehrende', 'lb.name as lehrberuf_name'])
            ->first();

        // Semester-Schnitte für Übersichtstabelle
        $semStats = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->groupBy('n.semester_id', 's.bezeichnung', 's.sortierung')
            ->select([
                's.bezeichnung as sem_label',
                's.sortierung',
                DB::raw('COUNT(*) as count'),
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0),2) as avg'),
            ])
            ->orderBy('s.sortierung')
            ->get();

        return view('berufsbildner.noten.index', [
            'notes'              => $notes,
            'lernende'           => $lernende,
            'selectedLernender'  => $selectedLernender,
            'selectedLernenderId' => $lernender_id,
            'kategorien'         => $kategorien,
            'semester'           => $semester,
            'lernenderProfil'    => $lernenderProfil,
            'semStats'           => $semStats,
        ]);
    }

    public function drucken(Request $request, int $lernender_id)
    {
        $user = $request->user();
        $bb   = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        // Betreuung prüfen
        $today = now()->toDateString();
        $selectedLernender = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->where('bt.berufsbildner_id', $bb->berufsbildner_id)
            ->where('bt.lernender_id', $lernender_id)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->select(['b.vorname', 'b.nachname'])
            ->first();

        abort_if(!$selectedLernender, 404);

        $profil = DB::table('lernende as l')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.lernender_id', $lernender_id)
            ->select(['l.lehrbeginn', 'l.lehrende', 'lb.name as lehrberuf_name'])
            ->first();

        $noten = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'n.kategorie_id')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->orderBy('s.sortierung')
            ->orderBy('n.pruefungsdatum')
            ->orderBy('n.note_id')
            ->select([
                'n.note_id', 'n.pruefungsdatum', 'n.note_wert', 'n.gewichtung_prozent', 'n.titel',
                's.semester_id', 's.bezeichnung as semester_bezeichnung', 's.sortierung',
                'k.name as kategorie_name',
                'f.name as fach_name',
                'm.modul_nummer', 'm.titel as modul_titel',
            ])
            ->get();

        $semesterNoten = $noten
            ->groupBy('semester_id')
            ->map(fn($items) => [
                'bezeichnung' => $items->first()->semester_bezeichnung,
                'noten'       => $items,
            ])
            ->values()
            ->toArray();

        return response()->view('lernender.noten.drucken', [
            'lernender'     => $selectedLernender,
            'profil'        => $profil,
            'semesterNoten' => $semesterNoten,
        ]);
    }

    public function markAlleGesehen(Request $request, int $lernender_id): RedirectResponse
    {
        $user = $request->user();
        $bb   = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        $today = now()->toDateString();
        $betreut = DB::table('betreuungen')
            ->where('berufsbildner_id', $bb->berufsbildner_id)
            ->where('lernender_id', $lernender_id)
            ->where('gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today))
            ->exists();

        abort_if(!$betreut, 403);

        // Alle aktiven Noten dieses Lernenden laden
        $noteIds = DB::table('noten')
            ->where('lernender_id', $lernender_id)
            ->whereNull('geloescht_am')
            ->pluck('note_id');

        $bbBenutzerId = (int) $user->benutzer_id;
        $now = now();

        $rows = $noteIds->map(fn($id) => [
            'note_id'            => $id,
            'viewer_benutzer_id' => $bbBenutzerId,
            'gesehen_am'         => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('noten_gesehen')->upsert($rows, ['note_id', 'viewer_benutzer_id'], ['gesehen_am']);
        }

        return redirect()
            ->route('berufsbildner.lernende.noten.index', ['lernender_id' => $lernender_id])
            ->with('status', 'Alle Noten als gesehen markiert.');
    }

    public function markGesehen(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $user = $request->user();
        $bb   = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        // Zugriff prüfen: Note muss zu einem betreuten Lernenden gehören
        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender_id)
            ->firstOrFail();

        $today = now()->toDateString();
        $betreut = DB::table('betreuungen')
            ->where('berufsbildner_id', $bb->berufsbildner_id)
            ->where('lernender_id', $lernender_id)
            ->where('gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today))
            ->exists();

        abort_if(!$betreut, 403);

        // Upsert: neuen Zeitstempel setzen (auch wenn bereits gesehen,
        // damit nach neuen Kommentaren der "Neu"-Badge korrekt verschwindet)
        DB::table('noten_gesehen')->upsert(
            [[
                'note_id'           => $note_id,
                'viewer_benutzer_id' => (int) $user->benutzer_id,
                'gesehen_am'        => now(),
            ]],
            ['note_id', 'viewer_benutzer_id'],
            ['gesehen_am']
        );

        return redirect()
            ->route('berufsbildner.lernende.noten.index', ['lernender_id' => $lernender_id])
            ->with('status', 'Note als gesehen markiert.');
    }
}
