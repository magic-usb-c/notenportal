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
}
