<?php

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\Kategorie;
use App\Services\Noten\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotenController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $lernenderId = (int) $lernender->lernender_id;

        // Semester-Liste (nur Lehrbeginn -> Lehrende/heute)
        $semester = $this->noteService->semestersForLernender($lernenderId);

        // Default-Semester: aktuelles (heute liegt drin), sonst das letzte in der Liste
        // Nullsafe-Operator verhindert Crash wenn kein Semester konfiguriert ist
        $selectedSemesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : (int) ($semester->firstWhere(function ($s) {
                $today = Carbon::today()->toDateString();
                return (string)$s->start_datum <= $today && (string)$s->end_datum >= $today;
            })?->semester_id ?? ($semester->last()?->semester_id ?? 0));

        // prev/next Semester (für Pfeile)
        $semesterIds = $semester->pluck('semester_id')->map(fn ($v) => (int) $v)->values();
        $idx = $semesterIds->search($selectedSemesterId);
        $prevSemesterId = ($idx !== false && $idx > 0) ? $semesterIds[$idx - 1] : null;
        $nextSemesterId = ($idx !== false && $idx < ($semesterIds->count() - 1)) ? $semesterIds[$idx + 1] : null;

        // Filter
        $kategorieId = $request->filled('kategorie_id') ? (int) $request->input('kategorie_id') : null;

        // Notes Query (immer fürs ausgewählte Semester; Kategorie optional)
        $q = $this->noteService->learnerNotesQuery($lernenderId);

        // Semester default immer gesetzt (wenn vorhanden)
        if ($selectedSemesterId > 0) {
            $q->where('semester_id', $selectedSemesterId);
        }

        $this->noteService->applyIndexFilters($q, $kategorieId, null);

        // Für Accordions: alle Noten als Collection (keine Pagination)
        // Kommentare + gesehen-Status für Badges werden mitgeladen
        $notes = (clone $q)
            ->with([
                'kommentare' => fn($q) => $q->with('autor')->orderBy('erstellt_am', 'asc'),
                'gesehen',
            ])
            ->get();

        // Summary
        [$avgUnweighted, $avgWeighted, $missingWeights, $count] = $this->noteService->calcAverages(
            $notes->map(fn ($n) => (object)[
                'note_wert' => $n->note_wert,
                'gewichtung_prozent' => $n->gewichtung_prozent,
            ])
        );

        $kategorien = Kategorie::query()->orderBy('sortierung')->get();

        // Gruppierung und Durchschnitte werden im View berechnet (nah an den Daten, keine Doppelstruktur)
        return view('lernender.noten.index', [
            'notes'              => $notes,
            'kategorien'         => $kategorien,
            'semester'           => $semester,
            'selectedSemesterId' => $selectedSemesterId,
            'prevSemesterId'     => $prevSemesterId,
            'nextSemesterId'     => $nextSemesterId,
            'avgUnweighted'      => $avgUnweighted,
            'avgWeighted'        => $avgWeighted,
            'missingWeights'     => $missingWeights,
            'count'              => $count,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        return view(
            'lernender.noten.create',
            $this->noteService->formOptionsForLernender((int) $lernender->lernender_id)
        );
    }

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
            'modul_id' => ['nullable', 'integer', 'exists:module,modul_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, (int) $lernender->lernender_id);

        \App\Models\Note::create([
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

        return redirect()->route('lernender.noten.index')->with('status', 'Note gespeichert.');
    }

    public function edit(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = \App\Models\Note::query()
            ->with(['fach', 'modulBelegung.modul', 'gruppe'])
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        return view('lernender.noten.edit', array_merge(
            ['note' => $note],
            $this->noteService->formOptionsForLernender((int) $lernender->lernender_id)
        ));
    }

    public function update(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = \App\Models\Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $validated = $request->validate([
            'kategorie_id' => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_id' => ['nullable', 'integer', 'exists:module,modul_id'],
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

        return redirect()->route('lernender.noten.index')->with('status', 'Note aktualisiert.');
    }

    /**
     * AJAX-Endpunkt: Note als gelesen markieren (feuert beim Öffnen des Detail-Accordions).
     * Gibt JSON zurück, damit kein Seiten-Reload nötig ist.
     */
    public function markGesehen(Request $request, int $note_id): JsonResponse
    {
        $user      = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            return response()->json(['ok' => false], 403);
        }

        // Sicherstellen, dass die Note dem angemeldeten Lernenden gehört
        $exists = \App\Models\Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->exists();

        if (!$exists) {
            return response()->json(['ok' => false], 404);
        }

        DB::table('noten_gesehen')->upsert(
            [[
                'note_id'            => $note_id,
                'viewer_benutzer_id' => (int) $user->benutzer_id,
                'gesehen_am'         => now(),
            ]],
            ['note_id', 'viewer_benutzer_id'],
            ['gesehen_am']
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = \App\Models\Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $note->delete();

        return redirect()->route('lernender.noten.index')->with('status', 'Note gelöscht.');
    }
}
