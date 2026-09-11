<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StammdatenFaecherController extends Controller
{
    public function index()
    {
        $faecher = DB::table('faecher as f')
            ->select([
                'f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'f.aktiv',
                'k.name as kategorie_name',
                DB::raw('COUNT(DISTINCT lbf.lehrberuf_id) as lehrberuf_count'),
            ])
            ->join('kategorien as k', 'k.kategorie_id', '=', 'f.kategorie_id')
            ->leftJoin('lehrberuf_faecher as lbf', 'lbf.fach_id', '=', 'f.fach_id')
            ->groupBy('f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'f.aktiv', 'k.name')
            ->orderBy('f.track_typ')
            ->orderBy('f.name')
            ->get();

        return view('admin.stammdaten.faecher.index', compact('faecher'));
    }

    public function create()
    {
        $kategorien = $this->aktiveKategorien();

        return view('admin.stammdaten.faecher.create', compact('kategorien'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kurzname' => ['required', 'string', 'max:50'],
            'kategorie_id' => ['required', 'integer', Rule::exists('kategorien', 'kategorie_id')->where('aktiv', 1)],
            'track_typ' => ['nullable', 'in:BMS,ABU'],
        ]);

        DB::table('faecher')->insert([
            'name' => $validated['name'],
            'kurzname' => strtoupper($validated['kurzname']),
            'kategorie_id' => $validated['kategorie_id'],
            'track_typ' => $validated['track_typ'] ?? null,
            'aktiv' => 1,
            'erstellt_am' => now(),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.master-data.subjects.index')
            ->with('success', 'Fach angelegt.');
    }

    public function edit(int $fach_id)
    {
        $fach = DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();
        $kategorien = $this->aktiveKategorien();

        return view('admin.stammdaten.faecher.edit', compact('fach', 'kategorien'));
    }

    public function update(Request $request, int $fach_id): RedirectResponse
    {
        DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kurzname' => ['required', 'string', 'max:50'],
            'kategorie_id' => ['required', 'integer', Rule::exists('kategorien', 'kategorie_id')->where('aktiv', 1)],
            'track_typ' => ['nullable', 'in:BMS,ABU'],
            'aktiv' => ['sometimes', 'boolean'],
        ]);

        DB::table('faecher')->where('fach_id', $fach_id)->update([
            'name' => $validated['name'],
            'kurzname' => strtoupper($validated['kurzname']),
            'kategorie_id' => $validated['kategorie_id'],
            'track_typ' => $validated['track_typ'] ?? null,
            'aktiv' => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.master-data.subjects.index')
            ->with('success', 'Fach aktualisiert.');
    }

    private function aktiveKategorien()
    {
        return DB::table('kategorien')->where('aktiv', 1)->orderBy('sortierung')->get();
    }
}
