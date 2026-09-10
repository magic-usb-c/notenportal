<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\Note;
use App\Services\Noten\NoteService;
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
        private readonly NoteService $noteService
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

        $statsRow = NotenGesehenController::gefilterteNoten($request, $lernender_id)
            ->selectRaw('COUNT(*) as total,
                ROUND(SUM(note_wert * COALESCE(gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(gewichtung_prozent,100)),0), 2) as avg_weighted,
                SUM(CASE WHEN note_wert >= 4.0 THEN 1 ELSE 0 END) as passed')
            ->first();

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

        $heute = now()->toDateString();
        $currentSemAvg = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->where('s.start_datum', '<=', $heute)
            ->where('s.end_datum', '>=', $heute)
            ->selectRaw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0),2) as avg')
            ->value('avg');

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
            'statsRow' => $statsRow,
            'neuCount' => $neuCount,
            'currentSemAvg' => $currentSemAvg !== null ? (float) $currentSemAvg : null,
            'notenVerlauf' => $this->noteService->notenVerlauf($lernender_id),
            'fachStats' => $this->noteService->fachModulStats($lernender_id, $kategorieId, $semesterId),
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

        $lernender->noten()->create([
            ...$this->notenfelder($daten),
            'erfasst_von_benutzer_id' => (int) $request->user()->benutzer_id,
            'aktualisiert_von_benutzer_id' => null,
        ]);

        return redirect()
            ->to($this->zuRoute($request, 'lernende.noten.index', $lernender_id))
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

        $note = $lernender->noten()->whereKey($note_id)->firstOrFail();
        $daten = $this->noteService->normalizeForSave($this->validiere($request), $lernender_id);

        $note->update([
            ...$this->notenfelder($daten),
            'aktualisiert_von_benutzer_id' => (int) $request->user()->benutzer_id,
        ]);

        return redirect()
            ->to($this->zuRoute($request, 'lernende.noten.index', $lernender_id))
            ->with('success', 'Note korrigiert.');
    }

    public function destroy(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('noteLoeschen', $lernender);

        $lernender->noten()->whereKey($note_id)->firstOrFail()->delete();

        return redirect()
            ->to($this->zuRoute($request, 'lernende.noten.index', $lernender_id))
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
