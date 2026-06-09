<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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

    /**
     * Admin: Formular für neue Note (für einen Lernenden) laden.
     */
    public function create(int $lernender_id)
    {
        $this->lernenderOr404($lernender_id);

        $formOptions = $this->noteService->formOptionsForLernender($lernender_id);

        return view('admin.noten.create', array_merge(
            ['lernender_id' => $lernender_id],
            $formOptions
        ));
    }

    /**
     * Admin: Neue Note für einen Lernenden speichern.
     */
    public function store(Request $request, int $lernender_id): RedirectResponse
    {
        $this->lernenderOr404($lernender_id);

        $validated = $request->validate([
            'kategorie_id'      => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ'               => ['required', 'in:fach,modul'],
            'fach_id'           => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_id'          => ['nullable', 'integer', 'exists:module,modul_id'],
            'titel'             => ['nullable', 'string', 'max:150'],
            'pruefungsdatum'    => ['required', 'date'],
            'note_wert'         => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, $lernender_id);

        Note::create([
            'lernender_id'               => $lernender_id,
            'kategorie_id'               => $data['kategorie_id'],
            'semester_id'                => $data['semester_id'],
            'fach_id'                    => $data['fach_id'],
            'modul_belegung_id'          => $data['modul_belegung_id'],
            'gruppe_id'                  => $data['gruppe_id'],
            'titel'                      => $data['titel'],
            'pruefungsdatum'             => $data['pruefungsdatum'],
            'note_wert'                  => $data['note_wert'],
            'gewichtung_prozent'         => $data['gewichtung_prozent'],
            'erfasst_von_benutzer_id'    => (int) $request->user()->benutzer_id,
            'aktualisiert_von_benutzer_id' => (int) $request->user()->benutzer_id,
        ]);

        return redirect()
            ->route('admin.lernende.noten.index', ['lernender_id' => $lernender_id])
            ->with('status', 'Note erfasst.');
    }

    /**
     * Admin: Formular zum Bearbeiten einer Note laden.
     */
    public function edit(int $lernender_id, int $note_id)
    {
        $note = Note::query()
            ->with(['fach', 'modulBelegung.modul', 'gruppe'])
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender_id)
            ->firstOrFail();

        $formOptions = $this->noteService->formOptionsForLernender($lernender_id);

        return view('admin.noten.edit', array_merge(
            ['note' => $note, 'lernender_id' => $lernender_id],
            $formOptions
        ));
    }

    /**
     * Admin: Note-Daten speichern (Korrekturen).
     */
    public function update(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender_id)
            ->firstOrFail();

        $validated = $request->validate([
            'kategorie_id'      => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ'               => ['required', 'in:fach,modul'],
            'fach_id'           => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_id'          => ['nullable', 'integer', 'exists:module,modul_id'],
            'titel'             => ['nullable', 'string', 'max:150'],
            'pruefungsdatum'    => ['required', 'date'],
            'note_wert'         => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, $lernender_id);

        $note->update([
            'kategorie_id'               => $data['kategorie_id'],
            'semester_id'                => $data['semester_id'],
            'fach_id'                    => $data['fach_id'],
            'modul_belegung_id'          => $data['modul_belegung_id'],
            'gruppe_id'                  => $data['gruppe_id'],
            'titel'                      => $data['titel'],
            'pruefungsdatum'             => $data['pruefungsdatum'],
            'note_wert'                  => $data['note_wert'],
            'gewichtung_prozent'         => $data['gewichtung_prozent'],
            'aktualisiert_von_benutzer_id' => (int) $request->user()->benutzer_id,
        ]);

        return redirect()
            ->route('admin.lernende.noten.index', ['lernender_id' => $lernender_id])
            ->with('status', 'Note aktualisiert.');
    }

    /**
     * Admin: Note soft-löschen (geloescht_am setzen).
     */
    public function destroy(int $lernender_id, int $note_id): RedirectResponse
    {
        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender_id)
            ->firstOrFail();

        $note->delete();

        return redirect()
            ->route('admin.lernende.noten.index', ['lernender_id' => $lernender_id])
            ->with('status', 'Note gelöscht.');
    }

    // ---------------------------------------------------------------------------

    private function lernenderOr404(int $lernender_id): object
    {
        $lernender = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->where('l.lernender_id', $lernender_id)
            ->whereNull('l.geloescht_am')
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname'])
            ->first();

        abort_if(!$lernender, 404);
        return $lernender;
    }
}
