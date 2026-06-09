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

        return view('berufsbildner.noten.index', [
            'notes'              => $notes,
            'lernende'           => $lernende,
            'selectedLernender'  => $selectedLernender,
            'selectedLernenderId' => $lernender_id,
            'kategorien'         => $kategorien,
            'semester'           => $semester,
        ]);
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
