<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'sortierung' => ['nullable', 'integer', 'min:0'],
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

        // Sortierung auto-berechnen falls nicht angegeben
        $sortierung = $validated['sortierung'] ?? null;
        if ($sortierung === null) {
            $max = DB::table('semester')->max('sortierung') ?? 0;
            $sortierung = (int) $max + 10;
        }

        DB::table('semester')->insert([
            'bezeichnung' => $validated['bezeichnung'],
            'start_datum' => $validated['start_datum'],
            'end_datum' => $validated['end_datum'],
            'sortierung' => $sortierung,
        ]);

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
        DB::table('semester')->where('semester_id', $semester_id)->firstOrFail();

        $validated = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:20',
                Rule::unique('semester', 'bezeichnung')->ignore($semester_id, 'semester_id')],
            'start_datum' => ['required', 'date'],
            'end_datum' => ['required', 'date', 'after:start_datum'],
            'sortierung' => ['required', 'integer', 'min:0'],
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

        DB::table('semester')->where('semester_id', $semester_id)->update($validated);

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

    /** Noten (auch gelöschte, sie hängen per Fremdschlüssel daran), Tracks oder Dokumente verweisen auf das Semester. */
    private function belegt(int $semester_id): bool
    {
        return DB::table('noten')->where('semester_id', $semester_id)->exists()
            || DB::table('lernender_tracks')->where(fn ($q) => $q->where('start_semester_id', $semester_id)->orWhere('end_semester_id', $semester_id))->exists()
            || DB::table('dokumente')->where('semester_id', $semester_id)->exists();
    }
}
