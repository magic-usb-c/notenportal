<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StammdatenLehrberufeController extends Controller
{
    public function index()
    {
        $lehrberufe = DB::table('lehrberufe')
            ->orderBy('name')
            ->get();

        return view('admin.stammdaten.lehrberufe.index', compact('lehrberufe'));
    }

    public function create()
    {
        return view('admin.stammdaten.lehrberufe.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kuerzel' => ['required', 'string', 'max:10', 'unique:lehrberufe,kuerzel'],
            'name'    => ['required', 'string', 'max:200', 'unique:lehrberufe,name'],
        ]);

        DB::table('lehrberufe')->insert([
            'kuerzel'         => strtoupper($validated['kuerzel']),
            'name'            => $validated['name'],
            'aktiv'           => 1,
            'erstellt_am'     => now(),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.stammdaten.lehrberufe.index')
            ->with('status', 'Lehrberuf angelegt.');
    }

    public function show(int $lehrberuf_id)
    {
        $lehrberuf = DB::table('lehrberufe')->where('lehrberuf_id', $lehrberuf_id)->firstOrFail();

        // Zugewiesene Module mit Pivot-Daten
        $zugewieseneModule = DB::table('lehrberuf_module as lbm')
            ->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->where('lbm.lehrberuf_id', $lehrberuf_id)
            ->select(['m.modul_id', 'm.modul_nummer', 'm.titel', 'lbm.pflicht', 'lbm.empfohlenes_lehrsemester_nr', 'lbm.aktiv'])
            ->orderBy('m.modul_nummer')
            ->get();

        $zugewieseneModulIds = $zugewieseneModule->pluck('modul_id')->all();

        // Noch nicht zugewiesene aktive Module
        $verfuegbareModule = DB::table('module')
            ->where('aktiv', 1)
            ->whereNotIn('modul_id', $zugewieseneModulIds)
            ->orderBy('modul_nummer')
            ->get();

        // Zugewiesene Fächer
        $zugewieseneFaecher = DB::table('lehrberuf_faecher as lbf')
            ->join('faecher as f', 'f.fach_id', '=', 'lbf.fach_id')
            ->where('lbf.lehrberuf_id', $lehrberuf_id)
            ->select(['f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'lbf.aktiv'])
            ->orderBy('f.track_typ')
            ->orderBy('f.name')
            ->get();

        $zugewieseneFachIds = $zugewieseneFaecher->pluck('fach_id')->all();

        // Noch nicht zugewiesene aktive Fächer
        $verfuegbareFaecher = DB::table('faecher')
            ->where('aktiv', 1)
            ->whereNotIn('fach_id', $zugewieseneFachIds)
            ->orderBy('track_typ')
            ->orderBy('name')
            ->get();

        return view('admin.stammdaten.lehrberufe.show', compact(
            'lehrberuf',
            'zugewieseneModule',
            'verfuegbareModule',
            'zugewieseneFaecher',
            'verfuegbareFaecher'
        ));
    }

    public function assignModul(Request $request, int $lehrberuf_id): RedirectResponse
    {
        $validated = $request->validate([
            'modul_id'                    => ['required', 'integer', 'exists:module,modul_id'],
            'pflicht'                     => ['boolean'],
            'empfohlenes_lehrsemester_nr' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        DB::table('lehrberuf_module')->insertOrIgnore([
            'lehrberuf_id'                => $lehrberuf_id,
            'modul_id'                    => $validated['modul_id'],
            'pflicht'                     => $request->boolean('pflicht', true) ? 1 : 0,
            'empfohlenes_lehrsemester_nr' => $validated['empfohlenes_lehrsemester_nr'] ?? null,
            'aktiv'                       => 1,
        ]);

        return back()->with('status', 'Modul zugewiesen.');
    }

    public function removeModul(int $lehrberuf_id, int $modul_id): RedirectResponse
    {
        DB::table('lehrberuf_module')
            ->where('lehrberuf_id', $lehrberuf_id)
            ->where('modul_id', $modul_id)
            ->delete();

        return back()->with('status', 'Modul entfernt.');
    }

    public function assignFach(Request $request, int $lehrberuf_id): RedirectResponse
    {
        $validated = $request->validate([
            'fach_id' => ['required', 'integer', 'exists:faecher,fach_id'],
        ]);

        DB::table('lehrberuf_faecher')->insertOrIgnore([
            'lehrberuf_id' => $lehrberuf_id,
            'fach_id'      => $validated['fach_id'],
            'aktiv'        => 1,
        ]);

        return back()->with('status', 'Fach zugewiesen.');
    }

    public function removeFach(int $lehrberuf_id, int $fach_id): RedirectResponse
    {
        DB::table('lehrberuf_faecher')
            ->where('lehrberuf_id', $lehrberuf_id)
            ->where('fach_id', $fach_id)
            ->delete();

        return back()->with('status', 'Fach entfernt.');
    }

    public function edit(int $lehrberuf_id)
    {
        $lehrberuf = DB::table('lehrberufe')->where('lehrberuf_id', $lehrberuf_id)->firstOrFail();
        return view('admin.stammdaten.lehrberufe.edit', compact('lehrberuf'));
    }

    public function update(Request $request, int $lehrberuf_id): RedirectResponse
    {
        DB::table('lehrberufe')->where('lehrberuf_id', $lehrberuf_id)->firstOrFail();

        $validated = $request->validate([
            'kuerzel' => ['required', 'string', 'max:10',
                \Illuminate\Validation\Rule::unique('lehrberufe', 'kuerzel')->ignore($lehrberuf_id, 'lehrberuf_id')],
            'name'    => ['required', 'string', 'max:200',
                \Illuminate\Validation\Rule::unique('lehrberufe', 'name')->ignore($lehrberuf_id, 'lehrberuf_id')],
            'aktiv'   => ['sometimes', 'boolean'],
        ]);

        DB::table('lehrberufe')->where('lehrberuf_id', $lehrberuf_id)->update([
            'kuerzel'         => strtoupper($validated['kuerzel']),
            'name'            => $validated['name'],
            'aktiv'           => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.stammdaten.lehrberufe.index')
            ->with('status', 'Lehrberuf aktualisiert.');
    }
}
