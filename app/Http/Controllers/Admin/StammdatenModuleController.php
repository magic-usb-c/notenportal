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
    private const array GRUPPIERUNGEN = ['lehrberuf', 'lernort'];

    public function index(Request $request)
    {
        $suche = trim((string) $request->input('suche', ''));
        $lehrberufId = $request->integer('lehrberuf_id') ?: null;
        $kategorieId = $request->integer('kategorie_id') ?: null;
        $gruppieren = in_array($request->input('gruppieren'), self::GRUPPIERUNGEN, true) ? $request->input('gruppieren') : '';

        $module = DB::table('module as m')
            ->select([
                'm.modul_id', 'm.modul_nummer', 'm.titel', 'm.version', 'm.aktiv',
                DB::raw('COUNT(DISTINCT lbm.lehrberuf_id) as lehrberuf_count'),
            ])
            ->leftJoin('lehrberuf_module as lbm', 'lbm.modul_id', '=', 'm.modul_id')
            ->when($suche !== '', function ($q) use ($suche) {
                $like = '%'.addcslashes($suche, '%_\\').'%';
                $q->where(fn ($w) => $w->where('m.modul_nummer', 'like', $like)->orWhere('m.titel', 'like', $like));
            })
            ->when($lehrberufId, fn ($q, $id) => $q->whereExists(fn ($e) => $e->select(DB::raw(1))
                ->from('lehrberuf_module as x')->whereColumn('x.modul_id', 'm.modul_id')->where('x.lehrberuf_id', $id)))
            ->when($kategorieId, fn ($q, $id) => $q->whereExists(fn ($e) => $e->select(DB::raw(1))
                ->from('lehrberuf_module as x')->whereColumn('x.modul_id', 'm.modul_id')->where('x.kategorie_id', $id)))
            ->groupBy('m.modul_id', 'm.modul_nummer', 'm.titel', 'm.version', 'm.aktiv')
            ->orderBy('m.modul_nummer')
            ->get();

        $lehrberufe = DB::table('lehrberufe')->orderBy('name')->get(['lehrberuf_id', 'name']);
        $kategorien = DB::table('kategorien')->orderBy('sortierung')->get(['kategorie_id', 'name']);

        $gruppen = collect();
        if ($gruppieren && $module->isNotEmpty()) {
            $spalte = $gruppieren === 'lehrberuf' ? 'lb.name' : 'k.name';
            $zuordnungen = DB::table('lehrberuf_module as lbm')
                ->join('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'lbm.lehrberuf_id')
                ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'lbm.kategorie_id')
                ->whereIn('lbm.modul_id', $module->pluck('modul_id'))
                ->select(['lbm.modul_id', DB::raw($spalte.' as gruppe')])
                ->get()
                ->groupBy('modul_id');

            foreach ($module as $m) {
                $namen = $zuordnungen->get($m->modul_id, collect())->pluck('gruppe')->filter()->unique();
                if ($namen->isEmpty()) {
                    $namen = collect([__('Ohne Zuordnung')]);
                }
                foreach ($namen as $name) {
                    $gruppen->put($name, ($gruppen->get($name) ?? collect())->push($m));
                }
            }
            $gruppen = $gruppen->sortKeys();
        }

        return view('admin.stammdaten.module.index', [
            'module' => $module,
            'gruppen' => $gruppen,
            'lehrberufe' => $lehrberufe,
            'kategorien' => $kategorien,
            'suche' => $suche,
            'lehrberufId' => $lehrberufId,
            'kategorieId' => $kategorieId,
            'gruppieren' => $gruppieren,
        ]);
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
            // Katalogversion des Modulbaukastens: nur sie macht den Verweis dorthin möglich.
            'version' => ['nullable', 'string', 'regex:/^\d{1,2}$/'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'ziel_gewicht_summe_default' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ]);

        DB::table('module')->insert([
            'modul_nummer' => strtoupper($validated['modul_nummer']),
            'titel' => $validated['titel'],
            'version' => $validated['version'] ?? null,
            'beschreibung' => $validated['beschreibung'] ?? null,
            'ziel_gewicht_summe_default' => $validated['ziel_gewicht_summe_default'] ?? 100.00,
            'aktiv' => 1,
            'erstellt_am' => now(),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.master-data.modules.index')
            ->with('success', __('Modul angelegt.'));
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
            'version' => ['nullable', 'string', 'regex:/^\d{1,2}$/'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'ziel_gewicht_summe_default' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'aktiv' => ['sometimes', 'boolean'],
        ]);

        DB::table('module')->where('modul_id', $modul_id)->update([
            'modul_nummer' => strtoupper($validated['modul_nummer']),
            'titel' => $validated['titel'],
            'version' => $validated['version'] ?? null,
            'beschreibung' => $validated['beschreibung'] ?? null,
            'ziel_gewicht_summe_default' => $validated['ziel_gewicht_summe_default'] ?? 100.00,
            'aktiv' => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.master-data.modules.index')
            ->with('success', __('Modul aktualisiert.'));
    }
}
