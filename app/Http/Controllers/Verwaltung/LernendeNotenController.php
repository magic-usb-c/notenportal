<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\Note;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Noten\NoteService;
use App\Services\Notifications\GradeWatcher;
use App\Services\Notifications\Messages\GradeCorrected;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Noten eines Lernenden: Übersicht, korrigieren (Admin + BB), anlegen/löschen (nur Admin).
 * Noten werden immer über den sichtbaren Lernenden geladen.
 */
class LernendeNotenController extends VerwaltungController
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly LernstandRechner $lernstaende,
        private readonly GradeWatcher $gradeWatcher = new GradeWatcher,
    ) {}

    public function index(Request $request, int $lernender_id): View
    {
        $user = $request->user();
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        $viewerId = (int) $user->benutzer_id;
        $kategorieId = $request->filled('kategorie_id') ? $request->integer('kategorie_id') : null;
        $semesterId = $request->filled('semester_id') ? $request->integer('semester_id') : null;

        $notes = $lernender->noten()
            ->with([
                ...Note::OVERVIEW_RELATIONS,
                'erfasstVonBenutzer',
                'aktualisiertVonBenutzer',
                'kommentare' => fn ($q) => $q->with('autor')->orderBy('erstellt_am'),
                'gesehen' => fn ($q) => $q->where('viewer_benutzer_id', $viewerId),
            ])
            ->when($kategorieId, fn ($q, $id) => $q->where('kategorie_id', $id))
            ->when($semesterId, fn ($q, $id) => $q->where('semester_id', $id))
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id')
            ->paginate(25)
            ->withQueryString();

        // Ungelesene Noten über alle Seiten im aktiven Filter – dieselbe Menge, die «Alle gesehen» markiert
        $neuCount = NotenGesehenController::gefilterteNoten($request, $lernender_id)
            ->leftJoin('noten_gesehen as g', fn ($j) => $j->on('g.note_id', '=', 'n.note_id')->where('g.viewer_benutzer_id', '=', $viewerId))
            ->where(fn ($q) => $q->whereNull('g.gesehen_am')
                ->orWhereColumn('n.erstellt_am', '>', 'g.gesehen_am')
                ->orWhereExists(fn ($k) => $k->select(DB::raw(1))
                    ->from('noten_kommentare as k')
                    ->whereColumn('k.note_id', 'n.note_id')
                    ->whereColumn('k.erstellt_am', '>', 'g.gesehen_am')))
            ->count();

        $stand = $this->lernstaende->fuer([$lernender_id])[$lernender_id];

        $switcher = Lernender::sichtbarFuer($user)
            ->whereHas('benutzer', fn ($q) => $q->where('aktiv', true))
            ->with('benutzer')
            ->get()
            ->push($lernender)
            ->unique('lernender_id')
            ->sortBy(fn (Lernender $l) => mb_strtolower($l->benutzer->nachname.' '.$l->benutzer->vorname))
            ->values();

        return view('verwaltung.noten.index', [
            'lernender' => $lernender->load('lehrberuf'),
            'notes' => $notes,
            'neuCount' => $neuCount,
            'stand' => $stand,
            'semesterId' => $semesterId,
            'switcher' => $switcher,
            'kategorien' => Kategorie::query()->orderBy('sortierung')->get(),
            'semester' => $this->noteService->semestersForLernender($lernender_id),
        ]);
    }

    public function create(Request $request, int $lernender_id): View
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('noteAnlegen', $lernender);

        return view('verwaltung.noten.create', [
            'lernender' => $lernender,
            'bezugOptionen' => $this->noteService->bezugOptionen($lernender_id),
            'semesterListe' => $this->noteService->semesterListe(),
        ]);
    }

    public function store(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('noteAnlegen', $lernender);

        $daten = $this->noteService->normalizeForSave($this->validiere($request), $lernender_id);

        $vorher = $this->gradeWatcher->schnappschuss($lernender_id);
        $lernender->noten()->create([
            ...$this->notenfelder($daten),
            'erfasst_von_benutzer_id' => (int) $request->user()->benutzer_id,
            'aktualisiert_von_benutzer_id' => null,
        ]);
        $this->gradeWatcher->pruefen($lernender_id, $vorher);

        return redirect()
            ->to($this->zuRoute($request, 'learners.grades.index', $lernender_id))
            ->with('success', 'Note erfasst.');
    }

    public function edit(Request $request, int $lernender_id, int $note_id): View
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('noteKorrigieren', $lernender);

        $note = $lernender->noten()->with(['fach', 'modulBelegung.modul'])->whereKey($note_id)->firstOrFail();

        return view('verwaltung.noten.edit', [
            'lernender' => $lernender,
            'note' => $note,
            'bezugOptionen' => $this->noteService->bezugOptionen($lernender_id),
            'semesterListe' => $this->noteService->semesterListe(),
        ]);
    }

    public function update(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('noteKorrigieren', $lernender);

        $note = $lernender->noten()->with(['fach', 'modulBelegung.modul'])->whereKey($note_id)->firstOrFail();
        $daten = $this->noteService->normalizeForSave($this->validiere($request), $lernender_id);

        $alterWert = (string) $note->note_wert;
        $vorher = $this->gradeWatcher->schnappschuss($lernender_id);
        $note->update([
            ...$this->notenfelder($daten),
            'aktualisiert_von_benutzer_id' => (int) $request->user()->benutzer_id,
        ]);
        $this->gradeWatcher->pruefen($lernender_id, $vorher);

        $lernender->loadMissing('benutzer');
        if ($lernender->benutzer && $alterWert !== (string) $note->note_wert) {
            Notifier::send($lernender->benutzer, NotificationCatalog::GRADE_CORRECTED, GradeCorrected::content(
                $note, $alterWert, route('learner.grades.index', ['_open' => $note->note_id])
            ));
        }

        return redirect()
            ->to($this->zuRoute($request, 'learners.grades.index', $lernender_id))
            ->with('success', 'Note korrigiert.');
    }

    public function destroy(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('noteLoeschen', $lernender);

        $lernender->noten()->whereKey($note_id)->firstOrFail()->delete();

        return redirect()
            ->to($this->zuRoute($request, 'learners.grades.index', $lernender_id))
            ->with('success', 'Note gelöscht.');
    }

    private function validiere(Request $request): array
    {
        return $request->validate([
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_id' => ['nullable', 'integer', 'exists:module,modul_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6', 'multiple_of:0.05'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function notenfelder(array $daten): array
    {
        return [
            'kategorie_id' => $daten['kategorie_id'],
            'semester_id' => $daten['semester_id'],
            'fach_id' => $daten['fach_id'],
            'modul_belegung_id' => $daten['modul_belegung_id'],
            'titel' => $daten['titel'],
            'pruefungsdatum' => $daten['pruefungsdatum'],
            'note_wert' => $daten['note_wert'],
            'gewichtung_prozent' => $daten['gewichtung_prozent'],
        ];
    }
}
