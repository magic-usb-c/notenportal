<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StammdatenLehrberufeController extends Controller
{
    public function index()
    {
        // Zähler je Lehrberuf: aktive Lernende (Konto aktiv), aktive Modul- und Fachzuordnungen
        $anzahl = fn (string $tabelle) => DB::table($tabelle)->whereColumn("{$tabelle}.lehrberuf_id", 'lb.lehrberuf_id')
            ->where("{$tabelle}.aktiv", true)->selectRaw('COUNT(*)');

        $lehrberufe = DB::table('lehrberufe as lb')
            ->select(['lb.lehrberuf_id', 'lb.kuerzel', 'lb.name', 'lb.aktiv'])
            ->selectSub(DB::table('lernende as l')->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
                ->whereColumn('l.lehrberuf_id', 'lb.lehrberuf_id')->whereNull('l.geloescht_am')->whereNull('b.geloescht_am')
                ->where('b.aktiv', true)->selectRaw('COUNT(*)'), 'lernende_anzahl')
            ->selectSub($anzahl('lehrberuf_module'), 'module_anzahl')
            ->selectSub($anzahl('lehrberuf_faecher'), 'faecher_anzahl')
            ->orderByDesc('lb.aktiv')
            ->orderBy('lb.name')
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
            'name' => ['required', 'string', 'max:200', 'unique:lehrberufe,name'],
        ]);

        DB::table('lehrberufe')->insert([
            'kuerzel' => strtoupper($validated['kuerzel']),
            'name' => $validated['name'],
            'aktiv' => 1,
            'erstellt_am' => now(),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.master-data.professions.index')
            ->with('success', __('Lehrberuf angelegt.'));
    }

    public function show(int $lehrberuf_id)
    {
        $lehrberuf = $this->lehrberuf($lehrberuf_id);
        $moduleInGebrauch = $this->moduleInGebrauch($lehrberuf_id);
        $faecherInGebrauch = $this->faecherInGebrauch($lehrberuf_id);

        $zugewieseneModule = DB::table('lehrberuf_module as lbm')
            ->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->where('lbm.lehrberuf_id', $lehrberuf_id)
            ->select(['m.modul_id', 'm.modul_nummer', 'm.titel', 'lbm.pflicht', 'lbm.empfohlenes_lehrsemester_nr', 'lbm.aktiv', 'lbm.kategorie_id'])
            ->orderBy('m.modul_nummer')
            ->get()
            ->each(function ($m) use ($moduleInGebrauch) {
                $m->in_gebrauch = in_array((int) $m->modul_id, $moduleInGebrauch, true);
            });

        // Noch nicht zugewiesene aktive Module
        $verfuegbareModule = DB::table('module')
            ->where('aktiv', 1)
            ->whereNotIn('modul_id', $zugewieseneModule->pluck('modul_id'))
            ->orderBy('modul_nummer')
            ->get(['modul_id', 'modul_nummer', 'titel']);

        $zugewieseneFaecher = DB::table('lehrberuf_faecher as lbf')
            ->join('faecher as f', 'f.fach_id', '=', 'lbf.fach_id')
            ->where('lbf.lehrberuf_id', $lehrberuf_id)
            ->select(['f.fach_id', 'f.name', 'f.kurzname', 'f.track_typ', 'lbf.aktiv'])
            ->orderBy('f.track_typ')
            ->orderBy('f.name')
            ->get()
            ->each(function ($f) use ($faecherInGebrauch) {
                $f->in_gebrauch = in_array((int) $f->fach_id, $faecherInGebrauch, true);
            });

        // Noch nicht zugewiesene aktive Fächer ohne Track (Track-Fächer sind über den Track freigegeben)
        $verfuegbareFaecher = DB::table('faecher')
            ->where('aktiv', 1)
            ->whereNull('track_typ')
            ->whereNotIn('fach_id', $zugewieseneFaecher->pluck('fach_id'))
            ->orderBy('name')
            ->get(['fach_id', 'kurzname', 'name']);

        // Aktive Lernorte, dazu ein inaktiver, solange ein Modul ihn noch trägt: sonst zeigte die Auswahl einen
        // anderen Lernort an und das nächste Speichern der Zeile verschöbe das Modul still dorthin.
        $kategorien = DB::table('kategorien')
            ->where(fn ($q) => $q->where('aktiv', 1)->orWhereIn('kategorie_id', $zugewieseneModule->pluck('kategorie_id')->filter()))
            ->orderBy('sortierung')
            ->get(['kategorie_id', 'code', 'name', 'aktiv']);

        return view('admin.stammdaten.lehrberufe.show', compact(
            'lehrberuf',
            'zugewieseneModule',
            'verfuegbareModule',
            'zugewieseneFaecher',
            'verfuegbareFaecher',
            'kategorien'
        ));
    }

    public function assignModul(Request $request, int $lehrberuf_id): RedirectResponse
    {
        $this->lehrberuf($lehrberuf_id);
        // Eigener Fehlerbeutel: die Felder heissen wie in den Tabellenzeilen, die Meldung gehört ins Sheet
        $validated = $request->validateWithBag('modul', [
            'modul_id' => ['required', 'integer', Rule::exists('module', 'modul_id')->where('aktiv', 1)],
            'kategorie_id' => ['required', 'integer', Rule::exists('kategorien', 'kategorie_id')->where('aktiv', 1)],
            'pflicht' => ['sometimes', 'boolean'],
            'empfohlenes_lehrsemester_nr' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        DB::table('lehrberuf_module')->insertOrIgnore([
            'lehrberuf_id' => $lehrberuf_id,
            'modul_id' => $validated['modul_id'],
            'kategorie_id' => $validated['kategorie_id'],
            'pflicht' => $request->boolean('pflicht', true) ? 1 : 0,
            'empfohlenes_lehrsemester_nr' => $validated['empfohlenes_lehrsemester_nr'] ?? null,
            'aktiv' => 1,
        ]);

        return back()->with('success', __('Modul zugewiesen.'));
    }

    /**
     * Zuweisung lösen – nur ohne Noten und Prüfungen von Lernenden dieses Berufs. Sonst fehlte ihnen der Lernort:
     * geplante Prüfungen fielen aus der Rechnung, bestehende Noten liessen sich nicht mehr bearbeiten.
     */
    public function removeModul(int $lehrberuf_id, int $modul_id): RedirectResponse
    {
        $this->lehrberuf($lehrberuf_id);

        if ($this->moduleInGebrauch($lehrberuf_id, $modul_id) !== []) {
            return back()->with('error', __('Zu diesem Modul gibt es in diesem Lehrberuf schon Noten oder Prüfungen. Deaktiviere es stattdessen.'));
        }

        DB::table('lehrberuf_module')
            ->where('lehrberuf_id', $lehrberuf_id)
            ->where('modul_id', $modul_id)
            ->delete();

        return back()->with('success', __('Modul entfernt.'));
    }

    /**
     * Lernort, Pflicht, Semester und Aktiv einer Zeile – jedes Feld speichert sofort. Ein Fehler kommt als Meldung
     * zurück, weil die Zeile kein Feld für ihn hat (die Feldnamen gehören dem Sheet «Modul hinzufügen»).
     */
    public function updateModulKategorie(Request $request, int $lehrberuf_id, int $modul_id): RedirectResponse
    {
        $zuweisung = DB::table('lehrberuf_module')->where('lehrberuf_id', $lehrberuf_id)->where('modul_id', $modul_id)->first();
        abort_if($zuweisung === null, 404);

        $validator = Validator::make($request->all(), [
            // Der bisherige Lernort bleibt wählbar, auch wenn er inzwischen inaktiv ist
            'kategorie_id' => ['required', 'integer', Rule::exists('kategorien', 'kategorie_id')
                ->where(fn ($q) => $q->where('aktiv', 1)->orWhere('kategorie_id', (int) $zuweisung->kategorie_id))],
            'pflicht' => ['sometimes', 'boolean'],
            'aktiv' => ['sometimes', 'boolean'],
            'empfohlenes_lehrsemester_nr' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
        ]);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }
        $validated = $validator->validated();
        $kategorieId = (int) $validated['kategorie_id'];

        DB::transaction(function () use ($validated, $lehrberuf_id, $modul_id, $kategorieId, $zuweisung) {
            // Nur übermittelte Felder ändern (Zeilenformular sendet alle, ältere Aufrufe nur den Lernort)
            DB::table('lehrberuf_module')
                ->where('lehrberuf_id', $lehrberuf_id)
                ->where('modul_id', $modul_id)
                ->update([
                    'kategorie_id' => $kategorieId,
                    ...array_map(fn ($v) => (bool) $v, array_intersect_key($validated, array_flip(['pflicht', 'aktiv']))),
                    ...array_intersect_key($validated, array_flip(['empfohlenes_lehrsemester_nr'])),
                ]);

            // Die Kategorie einer Note folgt aus dem Lernort (docs/notenlogik.md): bestehende Modulnoten der
            // Lernenden dieses Berufs ziehen mit, auch gelöschte, damit sie beim Wiederherstellen stimmen.
            if ($kategorieId !== (int) $zuweisung->kategorie_id) {
                DB::table('noten')
                    ->whereIn('modul_belegung_id', DB::table('modul_belegungen as mb')
                        ->join('lernende as l', 'l.lernender_id', '=', 'mb.lernender_id')
                        ->where('mb.modul_id', $modul_id)
                        ->where('l.lehrberuf_id', $lehrberuf_id)
                        ->select('mb.modul_belegung_id'))
                    ->update(['kategorie_id' => $kategorieId]);
            }
        });

        return back()->with('success', __('Modul aktualisiert.'));
    }

    public function assignFach(Request $request, int $lehrberuf_id): RedirectResponse
    {
        $this->lehrberuf($lehrberuf_id);
        $validated = $request->validateWithBag('fach', [
            'fach_id' => ['required', 'integer', Rule::exists('faecher', 'fach_id')->where('aktiv', 1)->whereNull('track_typ')],
        ]);

        DB::table('lehrberuf_faecher')->insertOrIgnore([
            'lehrberuf_id' => $lehrberuf_id,
            'fach_id' => $validated['fach_id'],
            'aktiv' => 1,
        ]);

        return back()->with('success', __('Fach zugewiesen.'));
    }

    /** Freigabe eines Fachs im Beruf ein- oder ausschalten; ausgeschaltet bleibt es bei bestehenden Noten stehen. */
    public function updateFach(Request $request, int $lehrberuf_id, int $fach_id): RedirectResponse
    {
        abort_unless(DB::table('lehrberuf_faecher')->where('lehrberuf_id', $lehrberuf_id)->where('fach_id', $fach_id)->exists(), 404);

        $validator = Validator::make($request->all(), ['aktiv' => ['required', 'boolean']]);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        DB::table('lehrberuf_faecher')
            ->where('lehrberuf_id', $lehrberuf_id)
            ->where('fach_id', $fach_id)
            ->update(['aktiv' => $request->boolean('aktiv')]);

        return back()->with('success', __('Fach aktualisiert.'));
    }

    /** Freigabe lösen – wie bei Modulen nur, solange Lernende dieses Berufs im Fach nichts erfasst haben. */
    public function removeFach(int $lehrberuf_id, int $fach_id): RedirectResponse
    {
        $this->lehrberuf($lehrberuf_id);

        if ($this->faecherInGebrauch($lehrberuf_id, $fach_id) !== []) {
            return back()->with('error', __('Zu diesem Fach gibt es in diesem Lehrberuf schon Noten oder Prüfungen. Deaktiviere es stattdessen.'));
        }

        DB::table('lehrberuf_faecher')
            ->where('lehrberuf_id', $lehrberuf_id)
            ->where('fach_id', $fach_id)
            ->delete();

        return back()->with('success', __('Fach entfernt.'));
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
                Rule::unique('lehrberufe', 'kuerzel')->ignore($lehrberuf_id, 'lehrberuf_id')],
            'name' => ['required', 'string', 'max:200',
                Rule::unique('lehrberufe', 'name')->ignore($lehrberuf_id, 'lehrberuf_id')],
            'aktiv' => ['sometimes', 'boolean'],
        ]);

        DB::table('lehrberufe')->where('lehrberuf_id', $lehrberuf_id)->update([
            'kuerzel' => strtoupper($validated['kuerzel']),
            'name' => $validated['name'],
            'aktiv' => (int) ($validated['aktiv'] ?? 1),
            'aktualisiert_am' => now(),
        ]);

        return redirect()->route('admin.master-data.professions.index')
            ->with('success', __('Lehrberuf aktualisiert.'));
    }

    private function lehrberuf(int $lehrberufId): object
    {
        return DB::table('lehrberufe')->where('lehrberuf_id', $lehrberufId)->firstOrFail();
    }

    /**
     * Module mit Noten oder Prüfungen von Lernenden dieses Berufs (optional nur ein Modul).
     *
     * @return list<int>
     */
    private function moduleInGebrauch(int $lehrberufId, ?int $modulId = null): array
    {
        $noten = DB::table('noten as n')
            ->join('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->join('lernende as l', 'l.lernender_id', '=', 'mb.lernender_id')
            ->where('l.lehrberuf_id', $lehrberufId)
            ->whereNull('n.geloescht_am')
            ->when($modulId, fn ($q, $id) => $q->where('mb.modul_id', $id))
            ->distinct()
            ->pluck('mb.modul_id');
        $pruefungen = DB::table('pruefungen as p')
            ->join('lernende as l', 'l.lernender_id', '=', 'p.lernender_id')
            ->where('l.lehrberuf_id', $lehrberufId)
            ->whereNotNull('p.modul_id')
            ->when($modulId, fn ($q, $id) => $q->where('p.modul_id', $id))
            ->distinct()
            ->pluck('p.modul_id');

        return $noten->merge($pruefungen)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Fächer ohne Track mit Noten oder Prüfungen von Lernenden dieses Berufs. Track-Fächer hängen nicht an der
     * Freigabe im Beruf, ihre Zuweisung darf immer weg.
     *
     * @return list<int>
     */
    private function faecherInGebrauch(int $lehrberufId, ?int $fachId = null): array
    {
        $basis = fn (string $tabelle, string $alias) => DB::table("{$tabelle} as {$alias}")
            ->join('lernende as l', 'l.lernender_id', '=', "{$alias}.lernender_id")
            ->join('faecher as f', 'f.fach_id', '=', "{$alias}.fach_id")
            ->where('l.lehrberuf_id', $lehrberufId)
            ->whereNull('f.track_typ')
            ->when($fachId, fn ($q, $id) => $q->where("{$alias}.fach_id", $id))
            ->distinct();

        return $basis('noten', 'n')->whereNull('n.geloescht_am')->pluck('n.fach_id')
            ->merge($basis('pruefungen', 'p')->pluck('p.fach_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
