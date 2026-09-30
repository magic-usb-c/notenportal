<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Konfiguration;
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
                'f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'f.aktiv', 'f.skala', 'f.zaehlt',
                'k.name as kategorie_name',
                DB::raw('COUNT(DISTINCT lbf.lehrberuf_id) as lehrberuf_count'),
                DB::raw('(SELECT COUNT(*) FROM noten n WHERE n.fach_id = f.fach_id AND n.geloescht_am IS NULL) as noten_count'),
            ])
            ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'f.kategorie_id')
            ->leftJoin('lehrberuf_faecher as lbf', 'lbf.fach_id', '=', 'f.fach_id')
            ->groupBy('f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'f.aktiv', 'f.skala', 'f.zaehlt', 'k.name')
            ->orderByDesc('f.aktiv')
            ->orderBy('k.sortierung')
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
        $validated = $this->validiere($request);

        DB::table('faecher')->insert([
            'name' => $validated['name'],
            'kurzname' => mb_strtoupper($validated['kurzname']),
            'kategorie_id' => $validated['kategorie_id'],
            'track_typ' => $validated['track_typ'] ?? null,
            'skala' => $validated['skala'] ?? 'note',
            'zaehlt' => (int) ($validated['zaehlt'] ?? 1),
            'aktiv' => 1,
            'erstellt_am' => now(),
            'aktualisiert_am' => now(),
        ]);
        Konfiguration::vergessen();

        return redirect()->route('admin.master-data.subjects.index')
            ->with('success', __('Fach angelegt.'));
    }

    public function edit(int $fach_id)
    {
        $fach = DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();
        $kategorien = $this->aktiveKategorien();
        $notenAnzahl = DB::table('noten')->where('fach_id', $fach_id)->whereNull('geloescht_am')->count();

        return view('admin.stammdaten.faecher.edit', compact('fach', 'kategorien', 'notenAnzahl'));
    }

    public function update(Request $request, int $fach_id): RedirectResponse
    {
        $fach = DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();
        $validated = $this->validiere($request, $fach_id);

        // Skala wechseln geht nur, solange keine Noten der anderen Art bestehen – sonst wären sie unlesbar.
        $skala = $validated['skala'] ?? $fach->skala;
        if ($skala !== $fach->skala) {
            $andere = DB::table('noten')->where('fach_id', $fach_id)->whereNull('geloescht_am')
                ->when($skala === 'stufe', fn ($q) => $q->whereNotNull('note_wert'), fn ($q) => $q->whereNotNull('note_stufe'))
                ->exists();
            if ($andere) {
                return back()->withInput()->withErrors(['skala' => __('Die Skala lässt sich nicht mehr wechseln, weil schon Noten erfasst sind.')]);
            }
        }

        DB::table('faecher')->where('fach_id', $fach_id)->update([
            'name' => $validated['name'],
            'kurzname' => mb_strtoupper($validated['kurzname']),
            'kategorie_id' => $validated['kategorie_id'],
            'track_typ' => $validated['track_typ'] ?? null,
            'skala' => $skala,
            'zaehlt' => (int) ($validated['zaehlt'] ?? $fach->zaehlt),
            'aktiv' => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);
        // Kategorie der bestehenden Noten folgt dem Fach (docs/notenlogik.md: «Kategorie folgt aus dem Fach»).
        DB::table('noten')->where('fach_id', $fach_id)->update(['kategorie_id' => $validated['kategorie_id']]);
        Konfiguration::vergessen();

        return redirect()->route('admin.master-data.subjects.index')
            ->with('success', __('Fach aktualisiert.'));
    }

    /**
     * Fach endgültig entfernen – nur solange nichts darauf verweist. Mit Noten, geplanten Prüfungen oder
     * Zielen bleibt nur das Deaktivieren, damit keine Note ihr Fach verliert.
     */
    public function destroy(int $fach_id): RedirectResponse
    {
        DB::table('faecher')->where('fach_id', $fach_id)->firstOrFail();

        $inGebrauch = DB::table('noten')->where('fach_id', $fach_id)->exists()
            || DB::table('pruefungen')->where('fach_id', $fach_id)->exists()
            || DB::table('ziele')->where('fach_id', $fach_id)->exists();

        if ($inGebrauch) {
            return back()->with('error', __('Das Fach hat bereits Noten oder Prüfungen. Deaktiviere es stattdessen.'));
        }

        DB::transaction(function () use ($fach_id) {
            DB::table('lehrberuf_faecher')->where('fach_id', $fach_id)->delete();
            DB::table('faecher')->where('fach_id', $fach_id)->delete();
        });
        Konfiguration::vergessen();

        return redirect()->route('admin.master-data.subjects.index')
            ->with('success', __('Fach gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validiere(Request $request, ?int $fachId = null): array
    {
        $track = $request->input('track_typ') ?: null;
        $eindeutig = fn (string $spalte) => Rule::unique('faecher', $spalte)
            ->where(fn ($q) => $track === null ? $q->whereNull('track_typ') : $q->where('track_typ', $track))
            ->ignore($fachId, 'fach_id');

        return $request->validate([
            'name' => ['required', 'string', 'max:200', $eindeutig('name')],
            'kurzname' => ['required', 'string', 'max:50', $eindeutig('kurzname')],
            'kategorie_id' => ['required', 'integer', Rule::exists('kategorien', 'kategorie_id')->where('aktiv', 1)],
            'track_typ' => ['nullable', 'in:BMS,ABU'],
            'skala' => ['sometimes', 'in:note,stufe'],
            'zaehlt' => ['sometimes', 'boolean'],
            'aktiv' => ['sometimes', 'boolean'],
        ], [
            'name.unique' => __('Ein Fach mit diesem Namen gibt es schon.'),
            'kurzname.unique' => __('Dieses Kürzel ist schon vergeben.'),
        ]);
    }

    private function aktiveKategorien()
    {
        return DB::table('kategorien')->where('aktiv', 1)->orderBy('sortierung')->get();
    }
}
