<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StammdatenModuleController extends Controller
{
    public function index()
    {
        $module = DB::table('module as m')
            ->select([
                'm.modul_id', 'm.modul_nummer', 'm.titel', 'm.aktiv',
                DB::raw('COUNT(DISTINCT lbm.lehrberuf_id) as lehrberuf_count'),
            ])
            ->leftJoin('lehrberuf_module as lbm', 'lbm.modul_id', '=', 'm.modul_id')
            ->groupBy('m.modul_id', 'm.modul_nummer', 'm.titel', 'm.aktiv')
            ->orderBy('m.modul_nummer')
            ->get();

        return view('admin.stammdaten.module.index', compact('module'));
    }

    public function create()
    {
        return view('admin.stammdaten.module.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'modul_nummer' => ['required', 'string', 'max:50', 'unique:module,modul_nummer'],
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'ziel_gewicht_summe_default' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ]);

        DB::table('module')->insert([
            'modul_nummer' => strtoupper($validated['modul_nummer']),
            'titel' => $validated['titel'],
            'beschreibung' => $validated['beschreibung'] ?? null,
            'ziel_gewicht_summe_default' => $validated['ziel_gewicht_summe_default'] ?? 100.00,
            'aktiv' => 1,
            'erstellt_am' => now(),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.stammdaten.module.index')
            ->with('success', 'Modul angelegt.');
    }

    public function edit(int $modul_id)
    {
        $modul = DB::table('module')->where('modul_id', $modul_id)->firstOrFail();

        return view('admin.stammdaten.module.edit', compact('modul'));
    }

    public function update(Request $request, int $modul_id): RedirectResponse
    {
        DB::table('module')->where('modul_id', $modul_id)->firstOrFail();

        $validated = $request->validate([
            'modul_nummer' => ['required', 'string', 'max:50',
                Rule::unique('module', 'modul_nummer')->ignore($modul_id, 'modul_id')],
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'ziel_gewicht_summe_default' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'aktiv' => ['sometimes', 'boolean'],
        ]);

        DB::table('module')->where('modul_id', $modul_id)->update([
            'modul_nummer' => strtoupper($validated['modul_nummer']),
            'titel' => $validated['titel'],
            'beschreibung' => $validated['beschreibung'] ?? null,
            'ziel_gewicht_summe_default' => $validated['ziel_gewicht_summe_default'] ?? 100.00,
            'aktiv' => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.stammdaten.module.index')
            ->with('success', 'Modul aktualisiert.');
    }
}
