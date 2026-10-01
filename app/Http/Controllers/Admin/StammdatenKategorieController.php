<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\KategorieRegeln;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StammdatenKategorieController extends Controller
{
    public function index()
    {
        $kategorien = DB::table('kategorien as k')
            ->select('k.*')
            ->selectSub(DB::table('faecher as f')->whereColumn('f.kategorie_id', 'k.kategorie_id')
                ->where('f.aktiv', true)->selectRaw('COUNT(*)'), 'faecher_anzahl')
            ->selectSub(DB::table('lehrberuf_module as lbm')->whereColumn('lbm.kategorie_id', 'k.kategorie_id')
                ->where('lbm.aktiv', true)->selectRaw('COUNT(DISTINCT lbm.modul_id)'), 'module_anzahl')
            ->orderByDesc('k.aktiv')
            ->orderBy('k.sortierung')
            ->get();

        return view('admin.stammdaten.kategorien.index', compact('kategorien'));
    }

    public function create()
    {
        return view('admin.stammdaten.kategorien.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:kategorien,code'],
            'name' => ['required', 'string', 'max:50', 'unique:kategorien,name'],
            'sortierung' => ['nullable', 'integer', 'min:0'],
            ...$this->rechenregelRules(),
        ]);

        $sortierung = $validated['sortierung'] ?? (DB::table('kategorien')->max('sortierung') ?? 0) + 1;

        DB::table('kategorien')->insert([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'sortierung' => (int) $sortierung,
            'aktiv' => true,
            ...$this->rechenregelWerte($validated),
        ]);

        return redirect()
            ->route('admin.master-data.categories.index')
            ->with('success', __('Kategorie angelegt.'));
    }

    public function edit(int $kategorie_id)
    {
        $kategorie = DB::table('kategorien')->where('kategorie_id', $kategorie_id)->firstOrFail();

        return view('admin.stammdaten.kategorien.edit', compact('kategorie'));
    }

    public function update(Request $request, int $kategorie_id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('kategorien', 'code')->ignore($kategorie_id, 'kategorie_id')],
            'name' => ['required', 'string', 'max:50', Rule::unique('kategorien', 'name')->ignore($kategorie_id, 'kategorie_id')],
            'sortierung' => ['nullable', 'integer', 'min:0'],
            'aktiv' => ['boolean'],
            ...$this->rechenregelRules(),
        ]);

        DB::table('kategorien')
            ->where('kategorie_id', $kategorie_id)
            ->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'sortierung' => (int) ($validated['sortierung'] ?? 0),
                'aktiv' => (bool) ($validated['aktiv'] ?? true),
                ...$this->rechenregelWerte($validated),
            ]);

        return redirect()
            ->route('admin.master-data.categories.index')
            ->with('success', __('Kategorie aktualisiert.'));
    }

    /**
     * Validierungsregeln für die Rechenregeln einer Kategorie (docs/notenlogik.md).
     */
    private function rechenregelRules(): array
    {
        return KategorieRegeln::regeln();
    }

    private function rechenregelWerte(array $validated): array
    {
        return KategorieRegeln::werte($validated);
    }
}
