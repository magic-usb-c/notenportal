<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Support\Modulbaukasten;
use App\Support\Modulkatalog;
use Illuminate\Support\Facades\DB;

/**
 * Der Gegenpart zu App\Services\Import\Katalogimport: schreibt den Katalog dieser Instanz in
 * dasselbe JSON, das der Import liest. Damit lässt sich ein eingelesener Katalog auf eine zweite
 * Instanz mitnehmen, ohne ihn erneut zu ernten – und ohne dass Katalogdaten im Repository liegen
 * (Rechtslage: docs/modulkatalog.md).
 *
 * Von der Kommandozeile (App\Console\Commands\ModulkatalogExport) und von der Katalogseite in den
 * Stammdaten gemeinsam genutzt, damit beide Wege dieselbe Datei erzeugen.
 */
final class Katalogexport
{
    /**
     * @param  bool  $mitEigenen  Auch selbst angelegte Module ausgeben (sie gelten auf der
     *                            Zielinstanz danach als Katalogmodul).
     * @param  list<string>  $nur  Nur diese Lehrberufe (exakter Name).
     * @return array<string, mixed> Leere Modulliste, wenn nichts auszugeben ist.
     */
    public function daten(bool $mitEigenen = false, array $nur = []): array
    {
        $module = $this->module($mitEigenen);

        return [
            'format' => Modulkatalog::FORMAT,
            'quelle' => 'export:'.config('app.name'),
            'geerntet_am' => now()->toIso8601String(),
            'abschluesse' => $module === [] ? [] : $this->abschluesse(array_keys($module), $nur),
            'module' => array_values($module),
        ];
    }

    /** Fertiges JSON, lesbar eingerückt – die Datei geht auch von Hand durch. */
    public function json(bool $mitEigenen = false, array $nur = []): string
    {
        return (string) json_encode(
            $this->daten($mitEigenen, $nur),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function dateiname(): string
    {
        return 'modulkatalog-'.now()->toDateString().'.json';
    }

    /**
     * Module samt Handlungszielen und LBV-Elementen, nach Modulnummer geordnet.
     *
     * @return array<string, array<string, mixed>> Modulnummer => Eintrag
     */
    private function module(bool $mitEigenen): array
    {
        $zeilen = DB::table('module')
            ->when(! $mitEigenen, fn ($q) => $q->where('quelle', Modulbaukasten::QUELLE))
            ->where('aktiv', 1)
            ->orderBy('modul_nummer')
            ->get();

        if ($zeilen->isEmpty()) {
            return [];
        }

        $ziele = DB::table('modul_handlungsziele')->orderBy('sortierung')->orderBy('nummer')
            ->get(['modul_id', 'nummer', 'text'])->groupBy('modul_id');
        $lbv = DB::table('modul_lbv_elemente')->orderBy('sortierung')->orderBy('lbv_element_id')->get()->groupBy('modul_id');

        $module = [];
        foreach ($zeilen as $m) {
            $module[$m->modul_nummer] = [
                'nummer' => $m->modul_nummer,
                'version' => $m->version,
                'titel' => $m->titel,
                'kompetenzfeld' => $m->kompetenzfeld,
                'kompetenz' => $m->kompetenz,
                'objekt' => $m->objekt,
                'publiziert_am' => $m->publiziert_am,
                'auslaufend' => (bool) $m->auslaufend,
                'handlungsziele' => $ziele->get($m->modul_id, collect())
                    ->map(fn ($z): array => ['nummer' => $z->nummer, 'text' => $z->text])->values()->all(),
                // Der Import erwartet die Elemente unter «lbv», nicht flach (Modulkatalog::modul()).
                'lbv' => ['elemente' => $lbv->get($m->modul_id, collect())->map(fn ($e): array => [
                    'bezeichnung' => $e->bezeichnung,
                    'gewichtung_prozent' => $e->gewichtung_prozent === null ? null : (float) $e->gewichtung_prozent,
                    'richtzeit' => $e->richtzeit === null ? null : (float) $e->richtzeit,
                    'pruefungsform' => $e->pruefungsform,
                    'sozialform' => $e->sozialform,
                    'beschreibung' => $e->beschreibung,
                ])->values()->all()],
            ];
        }

        return $module;
    }

    /**
     * Ein Abschluss je Lehrberuf mit seinen Modulzuordnungen. Module, die nicht ausgegeben werden
     * (etwa eigene ohne $mitEigenen), bleiben aus den Zuordnungen weg, sonst verweist die Datei
     * auf Module, die sie selbst nicht enthält.
     *
     * @param  list<string>  $nummern
     * @param  list<string>  $nur
     * @return list<array<string, mixed>>
     */
    private function abschluesse(array $nummern, array $nur): array
    {
        $erlaubt = array_flip($nummern);

        $zuordnungen = DB::table('lehrberuf_module as lm')
            ->join('module as m', 'm.modul_id', '=', 'lm.modul_id')
            ->where('lm.aktiv', 1)
            ->orderBy('m.modul_nummer')
            ->get([
                'lm.lehrberuf_id', 'lm.pflicht', 'lm.pflichtgrad', 'lm.version',
                'lm.empfohlenes_lehrsemester_nr', 'm.modul_nummer', 'm.titel', 'm.kompetenzfeld', 'm.auslaufend',
            ])->groupBy('lehrberuf_id');

        $abschluesse = [];
        foreach (DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get() as $beruf) {
            if ($nur !== [] && ! in_array($beruf->name, $nur, true)) {
                continue;
            }
            $module = [];
            foreach ($zuordnungen->get($beruf->lehrberuf_id, collect()) as $z) {
                if (! isset($erlaubt[$z->modul_nummer])) {
                    continue;
                }
                $semester = $z->empfohlenes_lehrsemester_nr;
                $module[] = [
                    'nummer' => $z->modul_nummer,
                    'version' => $z->version,
                    'titel' => $z->titel,
                    // Der Import leitet «pflicht» aus dem Grad ab; fehlt der Grad, bleibt nur der
                    // Schalter, also ergänzen wir den passenden Grad.
                    'pflichtgrad' => $z->pflichtgrad ?? ((int) $z->pflicht === 1 ? 'pfl' : 'wpfl'),
                    // Das Semester steht hier genau, weil es im Portal auch von Hand gesetzt sein kann
                    // (1–12); aus dem Lehrjahr allein wäre es nicht zurückzurechnen. Das Lehrjahr bleibt
                    // für Leser der Datei daneben stehen – der Import bevorzugt das Semester.
                    'semester' => $semester === null ? null : (int) $semester,
                    'lehrjahr' => $semester === null ? null : min(4, intdiv((int) $semester + 1, 2)),
                    'kompetenzfeld' => $z->kompetenzfeld,
                    'auslaufend' => (bool) $z->auslaufend,
                ];
            }
            if ($module === []) {
                continue;
            }
            $abschluesse[] = [
                'name' => $beruf->name,
                'kennung' => $beruf->quelle_kennung,
                'module' => $module,
            ];
        }

        return $abschluesse;
    }
}
