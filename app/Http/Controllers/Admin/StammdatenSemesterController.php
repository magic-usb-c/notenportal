<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StammdatenSemesterController extends Controller
{
    public function index()
    {
        $semester = DB::table('semester')
            ->orderBy('sortierung')
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
            'end_datum'   => ['required', 'date', 'after:start_datum'],
            'sortierung'  => ['nullable', 'integer', 'min:0'],
        ]);


        // Ueberlappende Semester fuehren zu mehrdeutiger Notenzuordnung
        // (NoteService::semesterForDate nimmt das erste Resultat).
        $overlap = DB::table('semester')
            ->where('start_datum', '<=', $validated['end_datum'])
            ->where('end_datum', '>=', $validated['start_datum'])
            ->first();
        if ($overlap) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_datum' => 'Zeitraum ueberschneidet sich mit Semester «' . $overlap->bezeichnung . '».',
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
            'end_datum'   => $validated['end_datum'],
            'sortierung'  => $sortierung,
        ]);

        return redirect()->route('admin.stammdaten.semester.index')
            ->with('status', 'Semester angelegt.');
    }

    public function edit(int $semester_id)
    {
        $semester = DB::table('semester')->where('semester_id', $semester_id)->firstOrFail();
        return view('admin.stammdaten.semester.edit', compact('semester'));
    }

    public function update(Request $request, int $semester_id): RedirectResponse
    {
        DB::table('semester')->where('semester_id', $semester_id)->firstOrFail();

        $validated = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('semester', 'bezeichnung')->ignore($semester_id, 'semester_id')],
            'start_datum' => ['required', 'date'],
            'end_datum'   => ['required', 'date', 'after:start_datum'],
            'sortierung'  => ['required', 'integer', 'min:0'],
        ]);

        $overlap = DB::table('semester')
            ->where('semester_id', '!=', $semester_id)
            ->where('start_datum', '<=', $validated['end_datum'])
            ->where('end_datum', '>=', $validated['start_datum'])
            ->first();
        if ($overlap) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_datum' => 'Zeitraum ueberschneidet sich mit Semester «' . $overlap->bezeichnung . '».',
            ]);
        }

        DB::table('semester')->where('semester_id', $semester_id)->update($validated);

        return redirect()->route('admin.stammdaten.semester.index')
            ->with('status', 'Semester aktualisiert.');
    }
}
