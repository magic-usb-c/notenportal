<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StammdatenFaecherController extends Controller
{
    public function index()
    {
        $faecher = DB::table('faecher as f')
            ->select([
                'f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'f.aktiv',
                DB::raw('COUNT(DISTINCT lbf.lehrberuf_id) as lehrberuf_count'),
            ])
            ->leftJoin('lehrberuf_faecher as lbf', 'lbf.fach_id', '=', 'f.fach_id')
            ->groupBy('f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'f.aktiv')
            ->orderBy('f.track_typ')
            ->orderBy('f.name')
            ->get();

        return view('admin.stammdaten.faecher.index', compact('faecher'));
    }

    public function create()
    {
        return view('admin.stammdaten.faecher.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:200'],
            'kurzname'  => ['required', 'string', 'max:50'],
            'track_typ' => ['required', 'in:BMS,ABU'],
        ]);

        DB::table('faecher')->insert([
            'name'            => $validated['name'],
            'kurzname'        => strtoupper($validated['kurzname']),
            'track_typ'       => $validated['track_typ'],
            'aktiv'           => 1,
            'erstellt_am'     => now(),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.stammdaten.faecher.index')
            ->with('status', 'Fach angelegt.');
    }

    public function edit(int $fach_id)
    {
        $fach = DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();
        return view('admin.stammdaten.faecher.edit', compact('fach'));
    }

    public function update(Request $request, int $fach_id): RedirectResponse
    {
        DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:200'],
            'kurzname'  => ['required', 'string', 'max:50'],
            'track_typ' => ['required', 'in:BMS,ABU'],
            'aktiv'     => ['sometimes', 'boolean'],
        ]);

        DB::table('faecher')->where('fach_id', $fach_id)->update([
            'name'            => $validated['name'],
            'kurzname'        => strtoupper($validated['kurzname']),
            'track_typ'       => $validated['track_typ'],
            'aktiv'           => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.stammdaten.faecher.index')
            ->with('status', 'Fach aktualisiert.');
    }
}
