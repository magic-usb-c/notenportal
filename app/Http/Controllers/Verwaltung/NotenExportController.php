<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Lernender;
use App\Services\Notenblatt;
use App\Support\Csv;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Druckansicht und CSV-Exporte – immer beschränkt auf sichtbare Lernende. */
class NotenExportController extends VerwaltungController
{
    public function drucken(Request $request, int $lernender_id, Notenblatt $notenblatt): Response
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);

        return response()->view('noten.notenblatt', [
            'blatt' => $notenblatt->fuer($lernender),
            'zurueck' => $this->zuRoute($request, 'learners.grades.index', $lernender_id),
        ]);
    }

    public function lernender(Request $request, int $lernender_id): StreamedResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);

        $rows = $this->notenQuery([$lernender_id])
            ->when($request->filled('semester_id'), fn ($q) => $q->where('n.semester_id', $request->integer('semester_id')))
            ->when($request->filled('kategorie_id'), fn ($q) => $q->where('n.kategorie_id', $request->integer('kategorie_id')))
            ->orderBy('s.sortierung')
            ->orderBy('n.pruefungsdatum')
            ->orderBy('n.note_id')
            ->get();

        $name = str($lernender->benutzer->nachname.'_'.$lernender->benutzer->vorname)->slug('_');

        return $this->csv($rows, 'noten_'.$name.'_'.now()->format('Ymd').'.csv', mitName: false);
    }

    public function alle(Request $request): StreamedResponse
    {
        $ids = Lernender::sichtbarFuer($request->user())->pluck('lernender_id')->all();

        $rows = $this->notenQuery($ids)
            ->join('lernende as l', 'l.lernender_id', '=', 'n.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->addSelect(['b.nachname', 'b.vorname'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->orderBy('s.sortierung')
            ->orderBy('n.pruefungsdatum')
            ->get();

        return $this->csv($rows, 'alle_noten_'.now()->format('Ymd_His').'.csv', mitName: true);
    }

    /** @param  list<int>  $lernendeIds */
    private function notenQuery(array $lernendeIds): Builder
    {
        return DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'n.kategorie_id')
            ->leftJoin('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->leftJoin('module as m', 'm.modul_id', '=', 'mb.modul_id')
            ->whereIn('n.lernender_id', $lernendeIds)
            ->whereNull('n.geloescht_am')
            ->select([
                'n.note_id', 'n.pruefungsdatum', 'n.note_wert', 'n.gewichtung_prozent', 'n.titel',
                's.semester_id', 's.bezeichnung as semester_bezeichnung', 's.sortierung',
                'k.name as kategorie_name',
                'f.name as fach_name',
                'm.modul_nummer', 'm.titel as modul_titel',
            ]);
    }

    private function csv(Collection $rows, string $dateiname, bool $mitName): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $mitName) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $kopf = ['Datum', 'Semester', 'Kategorie', 'Fach / Modul', 'Titel', 'Note', 'Gewichtung %'];
            fputcsv($out, $mitName ? ['Nachname', 'Vorname', ...$kopf] : $kopf, ';');

            foreach ($rows as $r) {
                $fachModul = $r->fach_name ?? ($r->modul_nummer ? $r->modul_nummer.' – '.$r->modul_titel : '');
                $zeile = [
                    $r->pruefungsdatum ? Carbon::parse($r->pruefungsdatum)->format('d.m.Y') : '',
                    $r->semester_bezeichnung,
                    $r->kategorie_name ?? '',
                    Csv::safe($fachModul),
                    Csv::safe($r->titel ?? ''),
                    number_format((float) $r->note_wert, 2, '.', ''),
                    $r->gewichtung_prozent ?? 100,
                ];

                fputcsv($out, $mitName ? [Csv::safe($r->nachname), Csv::safe($r->vorname), ...$zeile] : $zeile, ';');
            }

            fclose($out);
        }, $dateiname, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
