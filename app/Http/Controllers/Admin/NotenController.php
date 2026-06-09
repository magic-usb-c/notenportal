<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategorie;
use App\Models\Note;
use App\Services\Noten\NoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotenController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService
    ) {}

    /**
     * Admin: Noten eines ausgewählten Lernenden mit vollständigem Kommentar-Thread.
     *
     * Regeln:
     * - Admin darf alle Lernenden auswählen
     * - lernender_id muss existieren, sonst 404
     */
    public function index(Request $request, int $lernender_id)
    {
        // 1) Switcher-Liste: alle aktiven Lernenden
        $lernende = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select([
                'l.lernender_id',
                'b.vorname',
                'b.nachname',
                'b.email',
            ])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        // 2) Selected Lernender muss existieren
        $selectedLernender = $lernende->firstWhere('lernender_id', $lernender_id);
        if (!$selectedLernender) {
            abort(404);
        }

        // 3) Noten laden (inkl. vollständigem Kommentar-Thread für Accordion)
        $q = Note::query()
            ->with([
                'kategorie', 'semester', 'fach', 'modulBelegung.modul', 'gruppe',
                'kommentare' => fn($q) => $q->with('autor')->orderBy('erstellt_am', 'asc'),
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

        // 4) Filter Stammdaten
        $kategorien = Kategorie::query()->orderBy('sortierung')->get();

        // Nur Semester während der Lehrzeit dieses Lernenden
        $semester = $this->noteService->semestersForLernender($lernender_id);

        return view('admin.noten.index', [
            'notes' => $notes,

            // Switcher
            'lernende' => $lernende,
            'selectedLernender' => $selectedLernender,
            'selectedLernenderId' => $lernender_id,

            // Filter
            'kategorien' => $kategorien,
            'semester' => $semester,
        ]);
    }
}
