<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\Baum;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Auswertung\Notenbaum\BaumWechsel;
use App\Services\Auswertung\Notenbaum\Knoten;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notenbäume in den Stammdaten: Vorlage laden, Datei importieren und exportieren, Gewichte, Rundung
 * und Bestehensregeln je Knoten anpassen. Die Struktur (Knoten hinzufügen oder entfernen) ändert man
 * über Export, Bearbeiten der Datei und Import – dafür prüft BaumVorlage jede Datei vollständig.
 */
class StammdatenNotenbaeumeController extends Controller
{
    public function __construct(private readonly BaumVorlage $vorlagen) {}

    public function index(): View
    {
        $baeume = DB::table('notenbaeume as b')
            ->leftJoin('lehrberufe as l', 'l.lehrberuf_id', '=', 'b.lehrberuf_id')
            ->select(['b.baum_id', 'b.name', 'b.bezug', 'b.track_typ', 'b.aktiv', 'b.vorlage', 'b.erstellt_am', 'l.name as lehrberuf'])
            ->selectSub(fn ($q) => $q->from('notenbaum_positionen as p')->join('notenbaum_knoten as k', 'k.knoten_id', '=', 'p.knoten_id')
                ->whereColumn('k.baum_id', 'b.baum_id')->selectRaw('COUNT(*)'), 'positionen')
            ->orderByDesc('b.aktiv')->orderBy('l.name')->orderBy('b.track_typ')->orderByDesc('b.baum_id')
            ->get();

        return view('admin.stammdaten.notenbaeume.index', [
            'baeume' => $baeume,
            'vorlagen' => BaumVorlage::mitgeliefert(),
            'lehrberufe' => DB::table('lehrberufe')->orderBy('name')->get(['lehrberuf_id', 'name']),
        ]);
    }

    /** Mitgelieferte Vorlage laden. */
    public function laden(Request $request): RedirectResponse
    {
        $liste = BaumVorlage::mitgeliefert();
        $data = $request->validate([
            'vorlage' => ['required', Rule::in(array_keys($liste))],
            'lehrberuf_id' => ['nullable', 'integer', Rule::exists('lehrberufe', 'lehrberuf_id')],
        ]);

        // Die mitgelieferte Datei wird gegen die aktuellen Stammdaten geprüft (z. B. umbenannter Kategorie-Code)
        try {
            $vorlage = BaumVorlage::laden($data['vorlage']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->anlegen($vorlage, isset($data['lehrberuf_id']) ? (int) $data['lehrberuf_id'] : null, $data['vorlage']);
    }

    /** Eigene Datei importieren (z. B. ein exportierter und bearbeiteter Baum). */
    public function importieren(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'datei' => ['required', 'file', 'max:512'],
            'datei_lehrberuf_id' => ['nullable', 'integer', Rule::exists('lehrberufe', 'lehrberuf_id')],
        ]);

        try {
            $vorlage = BaumVorlage::lesen((string) file_get_contents($data['datei']->getRealPath()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['datei' => $e->getMessage()]);
        }

        return $this->anlegen($vorlage, isset($data['datei_lehrberuf_id']) ? (int) $data['datei_lehrberuf_id'] : null, null, 'datei_lehrberuf_id');
    }

    public function show(int $baum_id): View
    {
        $baum = DB::table('notenbaeume as b')->leftJoin('lehrberufe as l', 'l.lehrberuf_id', '=', 'b.lehrberuf_id')
            ->where('b.baum_id', $baum_id)->select(['b.*', 'l.name as lehrberuf'])->first() ?? abort(404);
        $knoten = DB::table('notenbaum_knoten as k')->leftJoin('kategorien as c', 'c.kategorie_id', '=', 'k.kategorie_id')
            ->where('k.baum_id', $baum_id)->orderBy('k.sortierung')->orderBy('k.knoten_id')
            ->get(['k.*', 'c.name as kategorie_name']);
        $faecher = DB::table('notenbaum_knoten_faecher as kf')->join('faecher as f', 'f.fach_id', '=', 'kf.fach_id')
            ->whereIn('kf.knoten_id', $knoten->pluck('knoten_id'))->orderBy('f.name')
            ->get(['kf.knoten_id', 'f.name'])->groupBy('knoten_id')->map(fn ($g) => $g->pluck('name')->all());

        // Flach in Baumreihenfolge mit Tiefe, damit die Tabelle ohne Rekursion in Blade auskommt
        $nachEltern = $knoten->groupBy(fn ($k) => $k->eltern_id ?? 0);
        $zeilen = [];
        $gehe = function (int $eltern, int $tiefe) use (&$gehe, &$zeilen, $nachEltern): void {
            $geschwister = $nachEltern[$eltern] ?? collect();
            $summe = $geschwister->where('zaehlt', 1)->sum(fn ($k) => (float) $k->gewicht);
            foreach ($geschwister as $k) {
                $zeilen[] = ['k' => $k, 'tiefe' => $tiefe, 'anteil' => $eltern !== 0 && $k->zaehlt && $summe > 0 ? (float) $k->gewicht / $summe : null];
                $gehe((int) $k->knoten_id, $tiefe + 1);
            }
        };
        $gehe(0, 0);

        $positionen = BaumWechsel::verlorenePositionen($baum_id);

        return view('admin.stammdaten.notenbaeume.show', compact('baum', 'zeilen', 'faecher', 'positionen'));
    }

    /** Name, Beschreibung und Parameter der Knoten. Typ und Struktur bleiben. */
    public function update(Request $request, int $baum_id): RedirectResponse
    {
        $baum = DB::table('notenbaeume')->where('baum_id', $baum_id)->first() ?? abort(404);
        $codes = DB::table('notenbaum_knoten')->where('baum_id', $baum_id)->pluck('code', 'knoten_id');
        $ids = $codes->keys()->map(fn ($v) => (int) $v)->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'beschreibung' => ['nullable', 'string', 'max:500'],
            'knoten' => ['required', 'array'],
            'knoten.*.name' => ['required', 'string', 'max:150'],
            'knoten.*.gewicht' => ['required', 'numeric', 'min:0', 'max:'.Knoten::GEWICHT_MAX],
            'knoten.*.rundung' => ['nullable', Rule::in(['0.1', '0.5', '1'])],
            'knoten.*.fallnote' => ['nullable', 'numeric', 'min:1', 'max:6'],
            'knoten.*.max_ungenuegend' => ['nullable', 'integer', 'min:0', 'max:'.Knoten::MAX_UNGENUEGEND_MAX],
            'knoten.*.max_minuspunkte' => ['nullable', 'numeric', 'min:0', 'max:'.Knoten::MAX_MINUSPUNKTE_MAX],
            'knoten.*.zaehlt' => ['sometimes', 'boolean'],
            'knoten.*.entfaellt_mit_track' => ['nullable', Rule::in(['BMS', 'ABU'])],
        ], [], $this->knotenAttribute($codes->all()));

        $fremd = array_diff(array_map('intval', array_keys($data['knoten'])), $ids);
        abort_if($fremd !== [], 422);

        DB::transaction(function () use ($data, $baum_id) {
            DB::table('notenbaeume')->where('baum_id', $baum_id)->update([
                'name' => $data['name'], 'beschreibung' => $data['beschreibung'] ?? null, 'aktualisiert_am' => now(),
            ]);
            foreach ($data['knoten'] as $id => $k) {
                DB::table('notenbaum_knoten')->where('baum_id', $baum_id)->where('knoten_id', (int) $id)->update([
                    'name' => $k['name'],
                    'gewicht' => (float) $k['gewicht'],
                    'rundung' => isset($k['rundung']) ? (float) $k['rundung'] : null,
                    'fallnote' => isset($k['fallnote']) ? (float) $k['fallnote'] : null,
                    'max_ungenuegend' => isset($k['max_ungenuegend']) ? (int) $k['max_ungenuegend'] : null,
                    'max_minuspunkte' => isset($k['max_minuspunkte']) ? (float) $k['max_minuspunkte'] : null,
                    'zaehlt' => (bool) ($k['zaehlt'] ?? false),
                    'entfaellt_mit_track' => $k['entfaellt_mit_track'] ?? null,
                ]);
            }
        });
        Konfiguration::vergessen();
        Protokoll::schreiben(Protokoll::ADMIN_NOTENBAUM_GEAENDERT, null, ['baum' => $baum->name]);

        return redirect()->route('admin.master-data.grade-trees.show', $baum_id)->with('success', __('Notenbaum gespeichert.'));
    }

    /**
     * Attributnamen der Knotenfelder für die Validierungsmeldungen, je Knoten mit dem Code – sonst steht
     * «Das Feld Name ist erforderlich.» da, ohne dass man erfährt, welcher Knoten gemeint ist.
     *
     * @param  array<int|string, string>  $codes  Code je Knoten-ID
     * @return array<string, string>
     */
    private function knotenAttribute(array $codes): array
    {
        $felder = [
            'name' => __('Name'),
            'gewicht' => __('Gewicht'),
            'fallnote' => __('Mindestnote'),
            'rundung' => __('Rundung'),
            'max_ungenuegend' => __('Max. ungenügend'),
            'max_minuspunkte' => __('Max. Minuspunkte'),
            'entfaellt_mit_track' => __('Entfällt mit'),
        ];

        $attribute = [];
        foreach ($felder as $feld => $bezeichnung) {
            $attribute["knoten.*.$feld"] = $bezeichnung;
            foreach ($codes as $id => $code) {
                $attribute["knoten.$id.$feld"] = __(':feld (Knoten :code)', ['feld' => $bezeichnung, 'code' => $code]);
            }
        }

        return $attribute;
    }

    /** Aktivieren löst den bisher aktiven Baum desselben Lehrberufs bzw. Bildungsgangs ab. */
    public function aktivieren(Request $request, int $baum_id): RedirectResponse
    {
        $baum = DB::table('notenbaeume')->where('baum_id', $baum_id)->first() ?? abort(404);
        $aktiv = $request->boolean('aktiv');

        $aktiv
            ? BaumWechsel::aktivieren((int) $baum->baum_id)
            : DB::table('notenbaeume')->where('baum_id', $baum->baum_id)->update(['aktiv' => false, 'aktualisiert_am' => now()]);
        Konfiguration::vergessen();
        Protokoll::schreiben(Protokoll::ADMIN_NOTENBAUM_GEAENDERT, null, ['baum' => $baum->name, 'aktiv' => $aktiv]);

        return back()->with('success', $aktiv ? __('Notenbaum aktiviert.') : __('Notenbaum deaktiviert.'));
    }

    public function exportieren(int $baum_id): Response
    {
        $daten = $this->vorlagen->exportieren($baum_id);
        $json = (string) json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $datei = 'notenbaum-'.(Str::slug((string) $daten['name']) ?: $baum_id).'.json';

        return response($json."\n", 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$datei.'"',
        ]);
    }

    /** Löschen nur ohne erfasste Positionen – sonst verlören Lernende ihre IPA- oder Prüfungsnoten. */
    public function destroy(int $baum_id): RedirectResponse
    {
        $baum = DB::table('notenbaeume')->where('baum_id', $baum_id)->first() ?? abort(404);
        if (BaumWechsel::verlorenePositionen($baum_id) > 0) {
            return back()->with('error', __('Zu diesem Notenbaum sind schon Noten erfasst. Deaktiviere ihn stattdessen.'));
        }

        DB::transaction(function () use ($baum_id) {
            // Kinder vor Eltern: die Selbstreferenz löscht MariaDB nicht in beliebiger Reihenfolge kaskadierend
            DB::table('notenbaum_knoten')->where('baum_id', $baum_id)->update(['eltern_id' => null]);
            DB::table('notenbaeume')->where('baum_id', $baum_id)->delete();
        });
        Konfiguration::vergessen();
        Protokoll::schreiben(Protokoll::ADMIN_NOTENBAUM_GELOESCHT, null, ['baum' => $baum->name]);

        return redirect()->route('admin.master-data.grade-trees.index')->with('success', __('Notenbaum gelöscht.'));
    }

    /** @param  array<string, mixed>  $vorlage */
    private function anlegen(array $vorlage, ?int $lehrberufId, ?string $schluessel, string $feld = 'lehrberuf_id'): RedirectResponse
    {
        $fuerLehrberuf = ($vorlage['bezug'] ?? Baum::LEHRBERUF) === Baum::LEHRBERUF;
        if ($fuerLehrberuf && $lehrberufId === null) {
            return back()->withInput()->withErrors([$feld => __('Für diese Vorlage einen Lehrberuf wählen.')]);
        }

        $id = $this->vorlagen->importieren($vorlage, $fuerLehrberuf ? $lehrberufId : null, $schluessel);
        Protokoll::schreiben(Protokoll::ADMIN_NOTENBAUM_GELADEN, null, ['baum' => $vorlage['name'], 'vorlage' => $schluessel]);

        return redirect()->route('admin.master-data.grade-trees.show', $id)
            ->with('success', __('Notenbaum «:name» geladen und aktiv.', ['name' => $vorlage['name']]));
    }

    /** Typen für die Anzeige. */
    public static function typName(string $typ): string
    {
        return match ($typ) {
            Knoten::GRUPPE => __('Gruppe'),
            Knoten::KATEGORIE => __('Kategorie'),
            Knoten::FAECHER => __('Fächer'),
            Knoten::MANUELL => __('Von Hand'),
            default => $typ,
        };
    }
}
