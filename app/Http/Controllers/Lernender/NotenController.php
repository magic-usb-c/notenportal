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

        // Deep-Link: ?_open=<note_id> wählt automatisch das Semester dieser Note,
        // damit der Accordion-Eintrag sichtbar ist (z.B. von Dashboard "Letzte Noten")
        $openNoteId = (int) $request->input('_open', 0);
        if ($openNoteId > 0 && !$request->filled('semester_id')) {
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

        // Semester-Übersicht (alle Semester dieses Lernenden)
        $semesterStats = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->groupBy('n.semester_id', 's.bezeichnung', 's.sortierung')
            ->select([
                'n.semester_id',
                's.bezeichnung as sem_label',
                's.sortierung',
                DB::raw('COUNT(*) as total'),
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0), 2) as avg_weighted'),
                DB::raw('SUM(CASE WHEN n.note_wert >= 4.0 THEN 1 ELSE 0 END) as passed'),
            ])
            ->orderBy('s.sortierung')
            ->get();

        // Gesamtdurchschnitt über alle Semester (gewichtet)
        $globalAvgWeighted = DB::table('noten')
            ->where('lernender_id', $lernenderId)
            ->whereNull('geloescht_am')
            ->selectRaw('ROUND(SUM(note_wert * COALESCE(gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(gewichtung_prozent,100)),0), 2) as avg')
            ->value('avg');

        $globalCount = DB::table('noten')
            ->where('lernender_id', $lernenderId)
            ->whereNull('geloescht_am')
            ->count();

        // Kategorie-Stats (für das ausgewählte Semester, nur Kategorien mit Noten)
        $kategorieStatsQ = DB::table('noten as n')
            ->join('kategorien as k', 'k.kategorie_id', '=', 'n.kategorie_id')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->groupBy('k.kategorie_id', 'k.name', 'k.sortierung')
            ->select([
                'k.kategorie_id',
                'k.name as kategorie_name',
                'k.sortierung',
                DB::raw('COUNT(*) as total'),
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0), 2) as avg_weighted'),
                DB::raw('SUM(CASE WHEN n.note_wert >= 4.0 THEN 1 ELSE 0 END) as passed'),
            ])
            ->orderBy('k.sortierung');

        if ($selectedSemesterId > 0) {
            $kategorieStatsQ->where('n.semester_id', $selectedSemesterId);
        }

        $kategorieStats = $kategorieStatsQ->get();

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
            'semesterStats'      => $semesterStats,
            'globalAvgWeighted'  => $globalAvgWeighted,
            'globalCount'        => $globalCount,
            'kategorieStats'     => $kategorieStats,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        // Basis für die Live-Ø-Vorschau: gewichtete Summen des aktuellen Semesters
        $today = now()->toDateString();
        $avgBasis = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->where('n.lernender_id', (int) $lernender->lernender_id)
            ->whereNull('n.geloescht_am')
            ->where('s.start_datum', '<=', $today)
            ->where('s.end_datum', '>=', $today)
            ->selectRaw('
                COALESCE(SUM(COALESCE(n.gewichtung_prozent, 100)), 0) as wsum,
                COALESCE(SUM(n.note_wert * COALESCE(n.gewichtung_prozent, 100)), 0) as nsum
            ')
            ->first();

        return view(
            'lernender.noten.create',
            array_merge(
                $this->noteService->formOptionsForLernender((int) $lernender->lernender_id),
                [
                    'avgBasisWsum' => (float) ($avgBasis->wsum ?? 0),
                    'avgBasisNsum' => (float) ($avgBasis->nsum ?? 0),
                ]
            )
        );
    }

    /**
     * Noten-Rechner: "Welche Note brauche ich, um Ziel-Ø zu erreichen?"
     * Übergibt sowohl die Noten des aktuellen Semesters als auch alle Noten an die View,
     * damit die Berechnung live im Browser (Alpine.js) erfolgen kann.
     */
    public function rechner(Request $request)
    {
        $lernender = $request->user()?->lernender;
        if (!$lernender) {
            abort(403);
        }

        $lernenderId = (int) $lernender->lernender_id;
        $today = Carbon::today()->toDateString();

        $currentSemester = DB::table('semester')
            ->where('start_datum', '<=', $today)
            ->where('end_datum', '>=', $today)
            ->first();

        $allNotes = DB::table('noten')
            ->where('lernender_id', $lernenderId)
            ->whereNull('geloescht_am')
            ->select(['note_wert', 'gewichtung_prozent'])
            ->get()
            ->map(fn ($n) => [
                'wert' => (float) $n->note_wert,
                'gew'  => $n->gewichtung_prozent !== null ? (float) $n->gewichtung_prozent : 100.0,
            ])
            ->values()
            ->all();

        $currentNotes = [];
        if ($currentSemester) {
            $currentNotes = DB::table('noten')
                ->where('lernender_id', $lernenderId)
                ->where('semester_id', $currentSemester->semester_id)
                ->whereNull('geloescht_am')
                ->select(['note_wert', 'gewichtung_prozent'])
                ->get()
                ->map(fn ($n) => [
                    'wert' => (float) $n->note_wert,
                    'gew'  => $n->gewichtung_prozent !== null ? (float) $n->gewichtung_prozent : 100.0,
                ])
                ->values()
                ->all();
        }

        return view('lernender.noten.rechner', [
            'allNotes'        => $allNotes,
            'currentNotes'    => $currentNotes,
            'currentSemester' => $currentSemester,
        ]);
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

        $params = $data['semester_id'] ? ['semester_id' => $data['semester_id']] : [];
        return redirect()->route('lernender.noten.index', $params)->with('status', 'Note gespeichert.');
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

        $params = $data['semester_id'] ? ['semester_id' => $data['semester_id']] : [];
        return redirect()->route('lernender.noten.index', $params)->with('status', 'Note aktualisiert.');
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

    /**
     * AJAX: Notiz/Titel einer eigenen Note inline aktualisieren.
     */
    public function updateTitel(Request $request, int $note_id): JsonResponse
    {
        $user      = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            return response()->json(['ok' => false], 403);
        }

        $note = \App\Models\Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', (int) $lernender->lernender_id)
            ->first();

        if (!$note) {
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
    public function drucken(Request $request)
    {
        $user = $request->user();
        $lernender = $user?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $lernenderId = (int) $lernender->lernender_id;

        $profil = DB::table('lernende as l')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.lernender_id', $lernenderId)
            ->select(['l.lehrbeginn', 'l.lehrende', 'lb.name as lehrberuf_name'])
            ->first();

        $noten = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'n.kategorie_id')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->where('n.lernender_id', $lernenderId)
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

        $lernenderObj = (object) [
            'vorname'  => $user->vorname,
            'nachname' => $user->nachname,
        ];

        return response()->view('lernender.noten.drucken', [
            'lernender'     => $lernenderObj,
            'profil'        => $profil,
            'semesterNoten' => $semesterNoten,
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $lernender = $user?->lernender;
        abort_if(!$lernender, 403);

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

        $filename = 'meine_noten_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Datum', 'Semester', 'Kategorie', 'Fach / Modul', 'Titel', 'Note', 'Gewichtung %'], ';');
            foreach ($rows as $r) {
                $fachModul = $r->fach_name
                    ?? ($r->modul_nummer ? $r->modul_nummer . ' – ' . $r->modul_titel : '');
                fputcsv($out, [
                    $r->pruefungsdatum ? \Carbon\Carbon::parse($r->pruefungsdatum)->format('d.m.Y') : '',
                    $r->semester,
                    $r->kategorie ?? '',
                    \App\Support\Csv::safe($fachModul),
                    \App\Support\Csv::safe($r->titel ?? ''),
                    number_format((float) $r->note_wert, 2, '.', ''),
                    $r->gewichtung_prozent ?? 100,
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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

        return redirect()
            ->route('lernender.noten.index', ['semester_id' => $note->semester_id])
            ->with('status', 'Note gelöscht.');
    }
}
