<?php

namespace App\Http\Controllers\Noten;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Services\Noten\NoteService;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService
    ) {}

    /**
     * Liste der Noten (nur eigene Noten des Lernenden).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $q = $this->noteService->learnerNotesQuery((int) $lernender->lernender_id);

        $kategorieId = $request->filled('kategorie_id') ? (int) $request->input('kategorie_id') : null;
        $semesterId  = $request->filled('semester_id') ? (int) $request->input('semester_id') : null;

        $this->noteService->applyIndexFilters($q, $kategorieId, $semesterId);

        $notes = (clone $q)->paginate(25)->withQueryString();

        $allForAvg = (clone $q)->get(['note_wert', 'gewichtung_prozent']);
        [$avgUnweighted, $avgWeighted, $missingWeights, $count] = $this->noteService->calcAverages($allForAvg);

        $kategorien = \App\Models\Kategorie::query()->orderBy('sortierung')->get();
        $semester   = \App\Models\Semester::query()->orderBy('sortierung')->get();

        return view('noten.index', compact(
            'notes',
            'kategorien',
            'semester',
            'avgUnweighted',
            'avgWeighted',
            'missingWeights',
            'count'
        ));
    }

    /**
     * Formular "Neue Note".
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        return view('noten.create', $this->noteService->formOptionsForLernender((int) $lernender->lernender_id));
    }

    /**
     * Note speichern (nur eigene).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $validated = $request->validate([
            'kategorie_id' => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_belegung_id' => ['nullable', 'integer', 'exists:modul_belegungen,modul_belegung_id'],
            'gruppe_id' => ['nullable', 'integer', 'exists:modul_note_gruppen,gruppe_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, (int) $lernender->lernender_id);

        Note::create([
            'lernender_id' => (int) $lernender->lernender_id,
            'kategorie_id' => $data['kategorie_id'],
            'semester_id' => $data['semester_id'],
            'fach_id' => $data['fach_id'],
            'modul_belegung_id' => $data['modul_belegung_id'],
            'gruppe_id' => $data['gruppe_id'],
            'titel' => $data['titel'],
            'pruefungsdatum' => $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'],
            'erfasst_von_benutzer_id' => (int) $user->benutzer_id,
            'aktualisiert_von_benutzer_id' => null,
        ]);

        return redirect()->route('noten.index')->with('status', 'Note gespeichert.');
    }

    /**
     * Formular "Note bearbeiten" (nur eigene).
     */
    public function edit(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = Note::query()
            ->with(['fach', 'modulBelegung.modul', 'gruppe'])
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        return view('noten.edit', array_merge(
            ['note' => $note],
            $this->noteService->formOptionsForLernender((int) $lernender->lernender_id)
        ));
    }

    /**
     * Note updaten (nur eigene).
     */
    public function update(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $validated = $request->validate([
            'kategorie_id' => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_belegung_id' => ['nullable', 'integer', 'exists:modul_belegungen,modul_belegung_id'],
            'gruppe_id' => ['nullable', 'integer', 'exists:modul_note_gruppen,gruppe_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, (int) $lernender->lernender_id);

        $note->update([
            'kategorie_id' => $data['kategorie_id'],
            'semester_id' => $data['semester_id'],
            'fach_id' => $data['fach_id'],
            'modul_belegung_id' => $data['modul_belegung_id'],
            'gruppe_id' => $data['gruppe_id'],
            'titel' => $data['titel'],
            'pruefungsdatum' => $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'],
            'aktualisiert_von_benutzer_id' => (int) $user->benutzer_id,
        ]);

        return redirect()->route('noten.index')->with('status', 'Note aktualisiert.');
    }

    /**
     * Note löschen (Soft-Delete, nur eigene).
     */
    public function destroy(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $note->delete();

        return redirect()->route('noten.index')->with('status', 'Note gelöscht.');
    }
}
