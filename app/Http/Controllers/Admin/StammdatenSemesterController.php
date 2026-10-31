<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StammdatenSemesterController extends Controller
{
    public function index()
    {
        $semester = DB::table('semester as s')
            ->select('s.*')
            ->selectSub(DB::table('noten as n')->whereColumn('n.semester_id', 's.semester_id')
                ->whereNull('n.geloescht_am')->selectRaw('COUNT(*)'), 'noten_anzahl')
            ->orderBy('s.sortierung')
            ->get();

        return view('admin.stammdaten.semester.index', compact('semester'));
    }

    public function create()
    {
        return view('admin.stammdaten.semester.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:20', 'unique:semester,bezeichnung'],
            'start_datum' => ['required', 'date'],
            'end_datum' => ['required', 'date', 'after:start_datum'],
            'sortierung' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        // Ueberlappende Semester fuehren zu mehrdeutiger Notenzuordnung
        // (NoteService::semesterForDate nimmt das erste Resultat).
        $overlap = DB::table('semester')
            ->where('start_datum', '<=', $validated['end_datum'])
            ->where('end_datum', '>=', $validated['start_datum'])
            ->first();
        if ($overlap) {
            throw ValidationException::withMessages([
                'start_datum' => __('Zeitraum überschneidet sich mit Semester «:bezeichnung».', ['bezeichnung' => $overlap->bezeichnung]),
            ]);
        }

        // Ohne Angabe chronologisch nach Startdatum einsortieren, eine bewusst gesetzte Sortierung bleibt
        DB::transaction(function () use ($validated) {
            DB::table('semester')->insert([
                'bezeichnung' => $validated['bezeichnung'],
                'start_datum' => $validated['start_datum'],
                'end_datum' => $validated['end_datum'],
                'sortierung' => $validated['sortierung'] ?? $this->sortierungNachDatum($validated['start_datum'], null),
            ]);
        });
        Konfiguration::vergessen();

        return redirect()->route('admin.master-data.semesters.index')
            ->with('success', __('Semester angelegt.'));
    }

    public function edit(int $semester_id)
    {
        $semester = DB::table('semester')->where('semester_id', $semester_id)->firstOrFail();
        $belegt = $this->belegt($semester_id);

        return view('admin.stammdaten.semester.edit', compact('semester', 'belegt'));
    }

    public function update(Request $request, int $semester_id): RedirectResponse
    {
        $bisher = DB::table('semester')->where('semester_id', $semester_id)->firstOrFail();

        $validated = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:20',
                Rule::unique('semester', 'bezeichnung')->ignore($semester_id, 'semester_id')],
            'start_datum' => ['required', 'date'],
            'end_datum' => ['required', 'date', 'after:start_datum'],
            'sortierung' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $overlap = DB::table('semester')
            ->where('semester_id', '!=', $semester_id)
            ->where('start_datum', '<=', $validated['end_datum'])
            ->where('end_datum', '>=', $validated['start_datum'])
            ->first();
        if ($overlap) {
            throw ValidationException::withMessages([
                'start_datum' => __('Zeitraum überschneidet sich mit Semester «:bezeichnung».', ['bezeichnung' => $overlap->bezeichnung]),
            ]);
        }

        // Leere oder unveränderte Sortierung ist keine bewusste Eingabe: ändert sich das Datum, rückt das Semester
        // an seinen chronologischen Platz. Eine geänderte Sortierung bleibt, wie sie eingegeben wurde.
        $bewusst = $validated['sortierung'] !== null && (int) $validated['sortierung'] !== (int) $bisher->sortierung;
        $datumGeaendert = substr((string) $bisher->start_datum, 0, 10) !== $validated['start_datum'];
        DB::transaction(function () use ($validated, $bisher, $semester_id, $bewusst, $datumGeaendert) {
            $sortierung = $bewusst ? (int) $validated['sortierung'] : (int) $bisher->sortierung;
            if (! $bewusst && $datumGeaendert && ! $this->chronologischRichtig($semester_id, $validated['start_datum'], $sortierung)) {
                $sortierung = $this->sortierungNachDatum($validated['start_datum'], $semester_id);
            }

            DB::table('semester')->where('semester_id', $semester_id)->update([
                'bezeichnung' => $validated['bezeichnung'],
                'start_datum' => $validated['start_datum'],
                'end_datum' => $validated['end_datum'],
                'sortierung' => $sortierung,
            ]);
        });
        Konfiguration::vergessen();

        return redirect()->route('admin.master-data.semesters.index')
            ->with('success', __('Semester aktualisiert.'));
    }

    /** Nur leere Semester: Noten und Tracks verweisen per Fremdschlüssel darauf. */
    public function destroy(int $semester_id): RedirectResponse
    {
        $semester = DB::table('semester')->where('semester_id', $semester_id)->first();
        abort_unless($semester, 404);

        if ($this->belegt($semester_id)) {
            return back()->with('error', __('Semester «:bezeichnung» enthält Noten, Tracks oder Dokumente und bleibt bestehen.', ['bezeichnung' => $semester->bezeichnung]));
        }

        DB::table('semester')->where('semester_id', $semester_id)->delete();

        return redirect()->route('admin.master-data.semesters.index')->with('success', __('Semester «:bezeichnung» gelöscht.', ['bezeichnung' => $semester->bezeichnung]));
    }

    /**
     * Sortierwert für ein Semester mit diesem Startdatum: direkt hinter dem chronologisch vorangehenden. Eine Lücke
     * (gelöschtes Semester) wird gefüllt, sonst rücken die späteren Semester um 10 nach. Gesetzte Werte und Lücken
     * bleiben sonst unangetastet; Lehrsemester::nummer zählt über die Sortierung.
     */
    private function sortierungNachDatum(string $startDatum, ?int $ausser): int
    {
        $andere = fn () => DB::table('semester')->when($ausser !== null, fn ($q) => $q->where('semester_id', '!=', $ausser));

        $vorgaenger = $andere()->where('start_datum', '<', $startDatum)->orderByDesc('start_datum')->value('sortierung');
        if ($vorgaenger === null) {
            $erster = $andere()->min('sortierung');
            if ($erster === null) {
                return 10;
            }
            $this->nachrueckenAb((int) $erster, $ausser);

            return (int) $erster;
        }

        $vorgaenger = (int) $vorgaenger;
        $naechster = $andere()->where('sortierung', '>', $vorgaenger)->min('sortierung');
        if ($naechster === null || (int) $naechster - $vorgaenger >= 20) {
            return $vorgaenger + 10;
        }

        $this->nachrueckenAb((int) $naechster, $ausser);

        return (int) $naechster;
    }

    private function nachrueckenAb(int $sortierung, ?int $ausser): void
    {
        DB::table('semester')
            ->where('sortierung', '>=', $sortierung)
            ->when($ausser !== null, fn ($q) => $q->where('semester_id', '!=', $ausser))
            ->increment('sortierung', 10);
    }

    /** Liegt das Semester mit dieser Sortierung schon zwischen seinem zeitlichen Vorgänger und Nachfolger? */
    private function chronologischRichtig(int $semesterId, string $startDatum, int $sortierung): bool
    {
        $andere = fn () => DB::table('semester')->where('semester_id', '!=', $semesterId);
        $vorgaenger = $andere()->where('start_datum', '<', $startDatum)->orderByDesc('start_datum')->value('sortierung');
        $nachfolger = $andere()->where('start_datum', '>', $startDatum)->orderBy('start_datum')->value('sortierung');

        return ($vorgaenger === null || (int) $vorgaenger < $sortierung)
            && ($nachfolger === null || (int) $nachfolger > $sortierung);
    }

    /** Noten (auch gelöschte, sie hängen per Fremdschlüssel daran), Tracks oder Dokumente verweisen auf das Semester. */
    private function belegt(int $semester_id): bool
    {
        return DB::table('noten')->where('semester_id', $semester_id)->exists()
            || DB::table('lernender_tracks')->where(fn ($q) => $q->where('start_semester_id', $semester_id)->orWhere('end_semester_id', $semester_id))->exists()
            || DB::table('dokumente')->where('semester_id', $semester_id)->exists();
    }
}
