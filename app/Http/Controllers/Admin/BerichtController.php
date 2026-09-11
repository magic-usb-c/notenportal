<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Lernstand;
use App\Services\Bericht;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Notenbericht über den Betrieb; ohne Semesterwahl gilt das laufende Semester, «alle» die ganze Lehrzeit. */
class BerichtController extends Controller
{
    private const array STATUS = [Lernstand::ROT => 'kritisch', Lernstand::GELB => 'beobachten', Lernstand::GRUEN => 'im Plan'];

    /** Übersetzte Anzeige der Status-Konstante für den CSV-Export. */
    private function statusText(string $status): string
    {
        return __(self::STATUS[$status]);
    }

    public function __construct(private readonly Bericht $bericht) {}

    public function noten(Request $request): View
    {
        $filter = $this->filter($request);
        $sort = in_array($request->input('sort'), Bericht::SORTIERUNGEN, true) ? (string) $request->input('sort') : 'status';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        return view('admin.berichte.noten', [
            ...$this->bericht->noten($filter, $sort, $dir),
            'filter' => $filter,
            'sort' => $sort,
            'dir' => $dir,
            'semester' => DB::table('semester')->where('start_datum', '<=', now()->toDateString())->orderByDesc('sortierung')->get(['semester_id', 'bezeichnung']),
            'lehrberufe' => DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get(['lehrberuf_id', 'name']),
            'berufsbildner' => DB::table('berufsbildner as bb')
                ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
                ->whereNull('bb.geloescht_am')
                ->whereNull('b.geloescht_am')
                ->where('b.aktiv', 1)
                ->orderBy('b.nachname')
                ->orderBy('b.vorname')
                ->get(['bb.berufsbildner_id', 'b.vorname', 'b.nachname']),
        ]);
    }

    public function notenExport(Request $request): StreamedResponse
    {
        $filter = $this->filter($request);
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

    /** @return array{semester_id: ?int, lehrberuf_id: ?int, berufsbildner_id: ?int} */
    private function filter(Request $request): array
    {
        $wahl = (string) $request->input('semester', '');

        return [
            'semester_id' => match (true) {
                $wahl === 'alle' => null,
                ctype_digit($wahl) => (int) $wahl,
                default => Konfiguration::ausDb()->semesterFuerDatum(now()->toDateString()),
            },
            'lehrberuf_id' => $request->integer('lehrberuf_id') ?: null,
            'berufsbildner_id' => $request->integer('berufsbildner_id') ?: null,
        ];
    }
}
