<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Lernstand;
use App\Services\Bericht;
use App\Support\Csv;
use App\Support\StatistikAntwort;
use App\Support\StatistikDaten;
use App\Support\StatistikFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Notenbericht über den Betrieb; ohne Semesterwahl gilt das laufende Semester, «alle» die ganze Lehrzeit. */
class BerichtController extends Controller
{
    /** Übersetzte Anzeige der Status-Konstante für den CSV-Export. */
    private function statusText(string $status): string
    {
        // Wörtlich übersetzt, damit die Schlüsselprüfung jeden Text findet.
        return match ($status) {
            Lernstand::ROT => __('kritisch'),
            Lernstand::GELB => __('beobachten'),
            Lernstand::ABGESCHLOSSEN => __('abgeschlossen'),
            default => __('im Plan'),
        };
    }

    public function __construct(
        private readonly Bericht $bericht,
        private readonly StatistikDaten $statistik,
    ) {}

    /** Notenbericht; JSON liefert Verteilung (S10) und Lehrjahresvergleich (S11) mit denselben Filtern wie die Seite. */
    public function noten(Request $request): Response|JsonResponse
    {
        $berufsbildner = $this->berufsbildnerListe();
        $statistikFilter = $this->statistikFilter($request, $berufsbildner);
        $filter = $this->filter($statistikFilter);
        $sort = (string) $statistikFilter->wert('sort');
        $dir = (string) $statistikFilter->wert('dir');

        $daten = $this->bericht->noten($filter, $sort, $dir);
        $paket = StatistikAntwort::paket($statistikFilter, $this->statistik->verteilung($daten['verteilung']), $this->statistik->lehrjahre($daten['nachLehrjahr']));
        if ($request->wantsJson()) {
            return StatistikAntwort::json($paket);
        }

        return StatistikAntwort::view('admin.berichte.noten', [
            ...$daten,
            'statistik' => $paket,
            'filter' => $filter,
            'sort' => $sort,
            'dir' => $dir,
            'semester' => DB::table('semester')->where('start_datum', '<=', now()->toDateString())->orderByDesc('sortierung')->get(['semester_id', 'bezeichnung', 'start_datum']),
            'lehrberufe' => DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get(['lehrberuf_id', 'name']),
            'berufsbildner' => $berufsbildner,
        ]);
    }

    public function notenExport(Request $request): StreamedResponse
    {
        $filter = $this->filter($this->statistikFilter($request));
        $zeilen = $this->bericht->noten($filter, 'name')['zeilen'];
        $mitSemester = $filter['semester_id'] !== null;
        $zahl = fn (?float $n) => $n !== null ? number_format($n, 1, '.', '') : '';

        return response()->streamDownload(function () use ($zeilen, $mitSemester, $zahl) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Nachname'), __('Vorname'), __('Lehrberuf'), __('Status'), __('Gesamtnote'), ...($mitSemester ? [__('Semesternote')] : []),
                __('Ungenügende Zeugnisnoten'), __('Prüfungen'), __('Letzte Note'), __('Gründe')], ';');

            foreach ($zeilen as $z) {
                fputcsv($out, [
                    Csv::safe($z->nachname), Csv::safe($z->vorname), Csv::safe((string) $z->lehrberuf),
                    $this->statusText($z->stand->status), $zahl($z->gesamt), ...($mitSemester ? [$zahl($z->semester)] : []),
                    $z->ungenuegend, $z->pruefungen, $z->letzte ? Carbon::parse($z->letzte)->format('d.m.Y') : '',
                    Csv::safe(implode(', ', $z->stand->gruende)),
                ], ';');
            }

            fclose($out);
        }, 'notenbericht_'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Filter der Seite als Whitelist: `semester` (alle oder eine ID, sonst das laufende), `lehrberuf_id`,
     * `berufsbildner_id`, `kategorie`, `lehrjahr`, `sort`, `dir`. Unbekannte Werte fallen auf den Standard.
     *
     * @param  Collection<int, object>  $berufsbildner
     */
    private function statistikFilter(Request $request, ?Collection $berufsbildner = null): StatistikFilter
    {
        $aktuell = Konfiguration::ausDb()->semesterFuerDatum(now()->toDateString());
        $berufsbildner ??= $this->berufsbildnerListe();

        return StatistikFilter::aus($request, [
            'semester' => ['alle', ...DB::table('semester')->pluck('semester_id')->map(fn ($id) => (int) $id)->all()],
            'lehrberuf_id' => ['id' => DB::table('lehrberufe')->pluck('lehrberuf_id')->map(fn ($id) => (int) $id)->all()],
            'berufsbildner_id' => ['id' => $berufsbildner->pluck('berufsbildner_id')->map(fn ($id) => (int) $id)->all()],
            'kategorie' => ['id' => array_map('intval', array_keys(Konfiguration::ausDb()->kategorien))],
            'lehrjahr' => [1, 2, 3, 4, 5],
            'sort' => Bericht::SORTIERUNGEN,
            'dir' => ['asc', 'desc'],
        ], ['semester' => $aktuell, 'sort' => 'status', 'dir' => 'asc']);
    }

    /** @return array{semester_id: ?int, lehrberuf_id: ?int, berufsbildner_id: ?int, lehrjahr: ?int, kategorie_id: ?int} */
    private function filter(StatistikFilter $f): array
    {
        return [
            'semester_id' => is_int($f->wert('semester')) ? $f->wert('semester') : null,
            'lehrberuf_id' => $f->wert('lehrberuf_id'),
            'berufsbildner_id' => $f->wert('berufsbildner_id'),
            'lehrjahr' => $f->wert('lehrjahr'),
            'kategorie_id' => $f->wert('kategorie'),
        ];
    }

    /** @return Collection<int, object> */
    private function berufsbildnerListe(): Collection
    {
        return DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get(['bb.berufsbildner_id', 'b.vorname', 'b.nachname']);
    }
}
