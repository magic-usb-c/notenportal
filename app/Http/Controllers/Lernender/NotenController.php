<?php

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Noten\NoteService;
use App\Services\Notenblatt;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\GradeWatcher;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Services\Uebersicht;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotenController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly NotenQuelle $quelle,
        private readonly Uebersicht $uebersicht,
        private readonly GradeWatcher $gradeWatcher = new GradeWatcher,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            abort(403);
        }

        $lernenderId = (int) $lernender->lernender_id;

        // Semester-Liste (nur Lehrbeginn -> Lehrende/heute)
        $semester = $this->noteService->semestersForLernender($lernenderId);

        // Deep-Link: ?_open=<note_id> wählt automatisch das Semester dieser Note,
        // damit der Accordion-Eintrag sichtbar ist (z.B. von Dashboard "Letzte Noten")
        $openNoteId = (int) $request->input('_open', 0);
        if ($openNoteId > 0 && ! $request->filled('semester_id')) {
            $openSemId = DB::table('noten')
                ->where('note_id', $openNoteId)
                ->where('lernender_id', $lernenderId)
                ->whereNull('geloescht_am')
                ->value('semester_id');
            if ($openSemId) {
                $request->merge(['semester_id' => (int) $openSemId]);
            }
        }

        // Default-Semester: aktuelles (heute liegt drin), sonst das letzte in der Liste
        // Nullsafe-Operator verhindert Crash wenn kein Semester konfiguriert ist
        $selectedSemesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : (int) ($semester->firstWhere(function ($s) {
                $today = Carbon::today()->toDateString();

                return (string) $s->start_datum <= $today && (string) $s->end_datum >= $today;
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
                'kommentare' => fn ($q) => $q->with('autor')->orderBy('erstellt_am', 'asc'),
                'gesehen',
            ])
            ->get();

        // Zeugnisnoten aus dem Rechenkern; Noten des Semesters nach Kategorie und Fach/Modul gruppiert
        $a = $this->quelle->auswertung($lernenderId);
        $kategorien = Kategorie::query()->orderBy('sortierung')->get()->keyBy('kategorie_id');
        $sem = $selectedSemesterId > 0 ? $selectedSemesterId : null;

        $gruppen = $notes->groupBy('kategorie_id')
            ->sortBy(fn ($_, $kid) => $kategorien[$kid]->sortierung ?? 0)
            ->map(fn ($items, $kid) => (object) [
                'id' => (int) $kid,
                'name' => $a->konfiguration->kategorieName((int) $kid),
                'semester' => $sem ? $a->semester($sem, (int) $kid)['note'] : null,
                'promotion' => $sem ? $a->promotion((int) $kid, $sem) : null,
                'elemente' => $items
                    ->groupBy(fn (Note $n) => $n->fach_id ? 'f'.$n->fach_id.'s'.$n->semester_id : 'm'.$n->modulBelegung?->modul_id)
                    ->map(fn ($noten, $schluessel) => (object) [
                        'element' => $a->elemente[$schluessel] ?? null,
                        'label' => $noten->first()->fach?->name
                            ?? trim(($noten->first()->modulBelegung?->modul?->modul_nummer ?? '').' '.($noten->first()->modulBelegung?->modul?->titel ?? '')),
                        'noten' => $noten->values(),
                    ])
                    ->sortBy('label')->values(),
            ])->values();

        $belegungen = DB::table('modul_belegungen')
            ->where('lernender_id', (int) $request->user()->lernender->lernender_id)
            ->orderBy('start_datum')
            ->orderBy('modul_belegung_id')
            ->get(['modul_id', 'end_datum'])
            ->groupBy('modul_id')
            ->map(fn ($liste) => ['offen' => $liste->last()->end_datum === null, 'versuche' => $liste->count()]);

        return view('lernender.noten.index', [
            'gruppen' => $gruppen,
            'auswertung' => $a,
            'heatmap' => $this->uebersicht->heatmap($a),
            'kategorien' => $kategorien->values(),
            'kategorieId' => $kategorieId,
            'semester' => $semester,
            'selectedSemesterId' => $selectedSemesterId,
            'prevSemesterId' => $prevSemesterId,
            'nextSemesterId' => $nextSemesterId,
            'anzahl' => $notes->count(),
            'belegungen' => $belegungen,
            'drawerFehler' => $this->noteService->drawerNachFehler($request, $lernender),
        ]);
    }

    public function create(Request $request)
    {
        $lernender = $request->user()?->lernender ?? abort(403);
        $pruefung = $request->filled('pruefung')
            ? $lernender->pruefungen()->with(['fach', 'modul'])->find($request->integer('pruefung'))
            : null;

        $daten = [
            'bezugOptionen' => $this->noteService->bezugOptionen((int) $lernender->lernender_id),
            'semesterListe' => $this->noteService->semesterListe(),
            'pruefung' => $pruefung,
            'vorauswahl' => preg_match('/^(fach|modul):\d+$/', (string) $request->query('bezug')) ? $request->query('bezug') : null,
        ];

        // ?drawer=1: nur das Formular für den Drawer der Notenliste
        return $request->boolean('drawer')
            ? view('lernender.noten.partials.formular', $daten + ['drawer' => 'neu'])
            : view('lernender.noten.create', $daten);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            abort(403);
        }

        $validated = $request->validate([
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_id' => ['nullable', 'integer', 'exists:module,modul_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6', 'multiple_of:0.05'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, (int) $lernender->lernender_id);

        $vorher = $this->gradeWatcher->schnappschuss((int) $lernender->lernender_id);
        $note = Note::create([
            'lernender_id' => (int) $lernender->lernender_id,
            'kategorie_id' => $data['kategorie_id'],
            'semester_id' => $data['semester_id'],
            'fach_id' => $data['fach_id'],
            'modul_belegung_id' => $data['modul_belegung_id'],
            'titel' => $data['titel'],
            'pruefungsdatum' => $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'],
            'erfasst_von_benutzer_id' => (int) $user->benutzer_id,
            'aktualisiert_von_benutzer_id' => null,
        ]);
        $this->gradeWatcher->pruefen((int) $lernender->lernender_id, $vorher);
        $this->benachrichtigeBetreuer($lernender, $note);

        // Aus einer Prüfung der Agenda eingetragen: Note hängt ab jetzt an der Prüfung
        if ($request->filled('pruefung_id')) {
            $lernender->pruefungen()->offen()->whereKey($request->integer('pruefung_id'))->update(['note_id' => $note->note_id]);
        }

        $params = $data['semester_id'] ? ['semester_id' => $data['semester_id']] : [];

        return redirect()->route('learner.grades.index', $params)->with('success', __('Note gespeichert.'));
    }

    /** Neue Note eines Lernenden → aktive Betreuer (GRADE_ADDED). */
    private function benachrichtigeBetreuer(Lernender $lernender, Note $note): void
    {
        $note->loadMissing(['fach', 'modulBelegung.modul']);
        foreach (Empfaenger::aktiveBetreuer((int) $lernender->lernender_id) as $betreuer) {
            Notifier::send($betreuer, NotificationCatalog::GRADE_ADDED, GradeAdded::einzeln(
                $lernender, $note, route('trainer.learners.show', $lernender->lernender_id)
            ));
        }
    }

    public function edit(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            abort(403);
        }

        $note = Note::query()
            ->with(['fach', 'modulBelegung.modul'])
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $daten = [
            'note' => $note,
            'bezugOptionen' => $this->noteService->bezugOptionen((int) $lernender->lernender_id),
            'semesterListe' => $this->noteService->semesterListe(),
        ];

        return $request->boolean('drawer')
            ? view('lernender.noten.partials.formular', $daten + ['drawer' => 'bearbeiten:'.$note->note_id])
            : view('lernender.noten.edit', $daten);
    }

    public function update(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            abort(403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $validated = $request->validate([
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_id' => ['nullable', 'integer', 'exists:module,modul_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6', 'multiple_of:0.05'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data = $this->noteService->normalizeForSave($validated, (int) $lernender->lernender_id);

        $vorher = $this->gradeWatcher->schnappschuss((int) $lernender->lernender_id);
        $note->update([
            'kategorie_id' => $data['kategorie_id'],
            'semester_id' => $data['semester_id'],
            'fach_id' => $data['fach_id'],
            'modul_belegung_id' => $data['modul_belegung_id'],
            'titel' => $data['titel'],
            'pruefungsdatum' => $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'],
            'aktualisiert_von_benutzer_id' => (int) $user->benutzer_id,
        ]);
        $this->gradeWatcher->pruefen((int) $lernender->lernender_id, $vorher);

        $params = $data['semester_id'] ? ['semester_id' => $data['semester_id']] : [];

        return redirect()->route('learner.grades.index', $params)->with('success', __('Note aktualisiert.'));
    }

    /**
     * AJAX-Endpunkt: Note als gelesen markieren (feuert beim Öffnen des Detail-Accordions).
     * Gibt JSON zurück, damit kein Seiten-Reload nötig ist.
     */
    public function markGesehen(Request $request, int $note_id): JsonResponse
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            return response()->json(['ok' => false], 403);
        }

        // Sicherstellen, dass die Note dem angemeldeten Lernenden gehört
        $exists = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->exists();

        if (! $exists) {
            return response()->json(['ok' => false], 404);
        }

        DB::table('noten_gesehen')->upsert(
            [[
                'note_id' => $note_id,
                'viewer_benutzer_id' => (int) $user->benutzer_id,
                'gesehen_am' => now(),
            ]],
            ['note_id', 'viewer_benutzer_id'],
            ['gesehen_am']
        );

        return response()->json(['ok' => true]);
    }

    /**
     * AJAX: Notiz/Titel einer eigenen Note inline aktualisieren.
     */
    public function updateTitel(Request $request, int $note_id): JsonResponse
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            return response()->json(['ok' => false], 403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->first();

        if (! $note) {
            return response()->json(['ok' => false], 404);
        }

        $validated = $request->validate([
            'titel' => ['nullable', 'string', 'max:150'],
        ]);

        $note->titel = ($validated['titel'] ?? '') !== '' ? $validated['titel'] : null;
        $note->aktualisiert_von_benutzer_id = (int) $user->benutzer_id;
        $note->save();

        return response()->json(['ok' => true, 'titel' => $note->titel]);
    }

    /**
     * Druckansicht: alle Noten sortiert nach Semester, ohne Layout-Shell.
     */
    public function drucken(Request $request, Notenblatt $notenblatt): Response
    {
        $lernender = $request->user()?->lernender;
        abort_if(! $lernender, 403);

        return response()->view('noten.notenblatt', [
            'blatt' => $notenblatt->fuer($lernender),
            'zurueck' => route('learner.grades.index'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $lernender = $user?->lernender;
        abort_if(! $lernender, 403);

        $lernenderId = (int) $lernender->lernender_id;

        $rows = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'n.kategorie_id')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->orderBy('s.sortierung')
            ->orderBy('n.pruefungsdatum')
            ->select([
                'n.pruefungsdatum',
                's.bezeichnung as semester',
                'k.name as kategorie',
                'f.name as fach_name',
                'm.modul_nummer', 'm.titel as modul_titel',
                'n.titel',
                'n.note_wert',
                'n.gewichtung_prozent',
            ])
            ->get();

        $filename = 'meine_noten_'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Datum'), __('Semester'), __('Kategorie'), __('Fach / Modul'), __('Titel'), __('Note'), __('Gewichtung %')], ';');
            foreach ($rows as $r) {
                $fachModul = $r->fach_name
                    ?? ($r->modul_nummer ? $r->modul_nummer.' – '.$r->modul_titel : '');
                fputcsv($out, [
                    $r->pruefungsdatum ? \Carbon\Carbon::parse($r->pruefungsdatum)->format('d.m.Y') : '',
                    $r->semester,
                    $r->kategorie ?? '',
                    Csv::safe($fachModul),
                    Csv::safe($r->titel ?? ''),
                    number_format((float) $r->note_wert, 2, '.', ''),
                    $r->gewichtung_prozent ?? 100,
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /** Modul neu belegen: aktuelle Belegung endet mit der letzten Note; die nächste Note eröffnet den neuen Versuch. */
    public function modulWiederholen(Request $request, int $modul_id): RedirectResponse
    {
        $lernender = $request->user()?->lernender;
        abort_if(! $lernender, 403);

        $belegung = ModulBelegung::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->where('modul_id', $modul_id)
            ->whereNull('end_datum')
            ->orderByDesc('start_datum')
            ->firstOrFail();

        $letzte = Note::query()->where('modul_belegung_id', $belegung->modul_belegung_id)->max('pruefungsdatum');
        $belegung->update(['end_datum' => Carbon::parse($letzte ?? now())->max($belegung->start_datum)->toDateString()]);

        return back()->with('success', __('Wiederholung gestartet.'));
    }

    /** Solange der neue Versuch keine Note hat, lässt sich die Wiederholung zurücknehmen. */
    public function modulFortsetzen(Request $request, int $modul_id): RedirectResponse
    {
        $lernender = $request->user()?->lernender;
        abort_if(! $lernender, 403);

        $letzte = ModulBelegung::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->where('modul_id', $modul_id)
            ->orderByDesc('start_datum')
            ->orderByDesc('modul_belegung_id')
            ->first();
        abort_if(! $letzte || $letzte->end_datum === null, 404);

        $letzte->update(['end_datum' => null]);

        return back()->with('success', __('Wiederholung zurückgenommen.'));
    }

    public function destroy(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (! $lernender) {
            abort(403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->firstOrFail();

        $note->delete();

        return redirect()
            ->route('learner.grades.index', ['semester_id' => $note->semester_id])
            ->with('success', __('Note gelöscht.'));
    }
}
