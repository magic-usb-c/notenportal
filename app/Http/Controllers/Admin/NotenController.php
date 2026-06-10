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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'erfasstVonBenutzer',
                'aktualisiertVonBenutzer',
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

        // Statistik über den gesamten gefilterten Datensatz (nicht nur aktuelle Seite)
        $statsRow = DB::table('noten as n')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->when($request->filled('kategorie_id'), fn($q) => $q->where('n.kategorie_id', (int) $request->input('kategorie_id')))
            ->when($request->filled('semester_id'),  fn($q) => $q->where('n.semester_id',  (int) $request->input('semester_id')))
            ->selectRaw('
                COUNT(*) as total,
                ROUND(SUM(note_wert * COALESCE(gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(gewichtung_prozent,100)),0), 2) as avg_weighted,
                SUM(CASE WHEN note_wert >= 4.0 THEN 1 ELSE 0 END) as passed
            ')
            ->first();

        // 4) Filter Stammdaten
        $kategorien = Kategorie::query()->orderBy('sortierung')->get();

        // Nur Semester während der Lehrzeit dieses Lernenden
        $semester = $this->noteService->semestersForLernender($lernender_id);

        // Notenverlauf für Liniendiagramm
        $notenVerlauf = $this->noteService->notenVerlauf($lernender_id);

        return view('admin.noten.index', [
            'notes' => $notes,
            'statsRow' => $statsRow,
            'notenVerlauf' => $notenVerlauf,

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
     * Admin: Druckansicht aller Noten eines Lernenden (alle Semester, ohne Filter).
     */
    public function drucken(int $lernender_id)
    {
        $lernender = $this->lernenderOr404($lernender_id);

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
            'lernender'     => $lernender,
            'profil'        => $profil,
            'semesterNoten' => $semesterNoten,
        ]);
    }

    /**
     * Admin: CSV-Export aller Noten eines Lernenden (mit optionalem Filter).
     */
    public function export(Request $request, int $lernender_id): StreamedResponse
    {
        $lernender = $this->lernenderOr404($lernender_id);

        $q = DB::table('noten as n')
            ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'n.kategorie_id')
            ->leftJoin('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->where('n.lernender_id', $lernender_id)
            ->whereNull('n.geloescht_am')
            ->orderBy('n.pruefungsdatum')
            ->orderBy('n.note_id')
            ->select([
                'n.note_id',
                'n.pruefungsdatum',
                's.bezeichnung as semester',
                'k.name as kategorie',
                'f.name as fach',
                'm.modul_nummer',
                'm.titel as modul_titel',
                'n.titel',
                'n.note_wert',
                'n.gewichtung_prozent',
            ]);

        if ($request->filled('semester_id')) {
            $q->where('n.semester_id', (int) $request->input('semester_id'));
        }

        if ($request->filled('kategorie_id')) {
            $q->where('n.kategorie_id', (int) $request->input('kategorie_id'));
        }

        $rows = $q->get();

        $filename = 'noten_' . str($lernender->nachname . '_' . $lernender->vorname)->slug('_') . '_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Datum', 'Semester', 'Kategorie', 'Fach / Modul', 'Titel', 'Note', 'Gewichtung %'], ';');

            foreach ($rows as $r) {
                $fachModul = $r->fach
                    ?? ($r->modul_nummer ? $r->modul_nummer . ' – ' . $r->modul_titel : '');

                fputcsv($out, [
                    $r->pruefungsdatum,
                    $r->semester ?? '',
                    $r->kategorie ?? '',
                    $fachModul,
                    $r->titel ?? '',
                    number_format((float) $r->note_wert, 1, '.', ''),
                    $r->gewichtung_prozent ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
