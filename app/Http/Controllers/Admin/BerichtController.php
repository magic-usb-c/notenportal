<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BerichtController extends Controller
{
    public function noten(Request $request)
    {
        $semesterId      = $request->filled('semester_id')      ? (int) $request->input('semester_id')      : null;
        $lehrberufId     = $request->filled('lehrberuf_id')     ? (int) $request->input('lehrberuf_id')     : null;
        $berufsbildnerId = $request->filled('berufsbildner_id') ? (int) $request->input('berufsbildner_id') : null;

        $today = now()->toDateString();

        $lernendeQ = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'lb.name as lehrberuf', 'lb.kuerzel'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname');

        if ($lehrberufId) {
            $lernendeQ->where('l.lehrberuf_id', $lehrberufId);
        }

        if ($berufsbildnerId) {
            $lernendeQ->whereIn('l.lernender_id', function ($sub) use ($berufsbildnerId, $today) {
                $sub->select('lernender_id')
                    ->from('betreuungen')
                    ->where('berufsbildner_id', $berufsbildnerId)
                    ->where('gueltig_von', '<=', $today)
                    ->where(fn($w) => $w->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today));
            });
        }

        $lernende = $lernendeQ->get();
        $ids = $lernende->pluck('lernender_id')->map(fn($v) => (int) $v)->all();

        $statsQ = DB::table('noten as n')
            ->whereIn('n.lernender_id', $ids)
            ->whereNull('n.geloescht_am')
            ->groupBy('n.lernender_id')
            ->select([
                'n.lernender_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN n.note_wert >= 4.0 THEN 1 ELSE 0 END) as passed'),
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0), 2) as avg_weighted'),
                DB::raw('MAX(n.pruefungsdatum) as last_entry'),
            ]);

        if ($semesterId) {
            $statsQ->where('n.semester_id', $semesterId);
        }

        $stats = $statsQ->get()->keyBy('lernender_id');

        $semester        = DB::table('semester')->orderBy('sortierung')->get();
        $lehrberufe      = DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get();
        $berufsbildner   = DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bb.berufsbildner_id', 'b.vorname', 'b.nachname'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        $alleNoten    = $stats->values();
        $gesamtTotal  = $alleNoten->sum('total');
        $gesamtPassed = $alleNoten->sum('passed');
        $gesamtAvg    = $alleNoten->filter(fn($s) => $s->avg_weighted !== null)->avg('avg_weighted');

        return view('admin.berichte.noten', [
            'lernende'        => $lernende,
            'stats'           => $stats,
            'semester'        => $semester,
            'lehrberufe'      => $lehrberufe,
            'berufsbildner'   => $berufsbildner,
            'semesterId'      => $semesterId,
            'lehrberufId'     => $lehrberufId,
            'berufsbildnerId' => $berufsbildnerId,
            'gesamtTotal'     => $gesamtTotal,
            'gesamtPassed'    => $gesamtPassed,
            'gesamtAvg'       => $gesamtAvg !== null ? round((float) $gesamtAvg, 2) : null,
        ]);
    }

    public function notenExport(Request $request): StreamedResponse
    {
        $semesterId      = $request->filled('semester_id')      ? (int) $request->input('semester_id')      : null;
        $lehrberufId     = $request->filled('lehrberuf_id')     ? (int) $request->input('lehrberuf_id')     : null;
        $berufsbildnerId = $request->filled('berufsbildner_id') ? (int) $request->input('berufsbildner_id') : null;

        $today = now()->toDateString();

        $lernendeQ = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'lb.name as lehrberuf'])
            ->orderBy('b.nachname')->orderBy('b.vorname');

        if ($lehrberufId) {
            $lernendeQ->where('l.lehrberuf_id', $lehrberufId);
        }

        if ($berufsbildnerId) {
            $lernendeQ->whereIn('l.lernender_id', function ($sub) use ($berufsbildnerId, $today) {
                $sub->select('lernender_id')
                    ->from('betreuungen')
                    ->where('berufsbildner_id', $berufsbildnerId)
                    ->where('gueltig_von', '<=', $today)
                    ->where(fn($w) => $w->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today));
            });
        }

        $lernende = $lernendeQ->get();
        $ids = $lernende->pluck('lernender_id')->map(fn($v) => (int) $v)->all();

        $statsQ = DB::table('noten as n')
            ->whereIn('n.lernender_id', $ids)
            ->whereNull('n.geloescht_am')
            ->groupBy('n.lernender_id')
            ->select([
                'n.lernender_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN n.note_wert >= 4.0 THEN 1 ELSE 0 END) as passed'),
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent,100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent,100)),0), 2) as avg_weighted'),
                DB::raw('MAX(n.pruefungsdatum) as last_entry'),
            ]);

        if ($semesterId) {
            $statsQ->where('n.semester_id', $semesterId);
        }

        $stats    = $statsQ->get()->keyBy('lernender_id');
        $filename = 'notenuebersicht_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($lernende, $stats) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Vorname', 'Lehrberuf', 'Noten', 'Ø gewichtet', 'Bestanden', 'Quote %', 'Letzte Note'], ';');

            foreach ($lernende as $l) {
                $s      = $stats->get((int) $l->lernender_id);
                $total  = $s?->total  ?? 0;
                $passed = $s?->passed ?? 0;
                $avg    = $s?->avg_weighted !== null ? number_format((float) $s->avg_weighted, 2, '.', '') : '';
                $quote  = $total > 0 ? round($passed / $total * 100) : '';
                $last   = $s?->last_entry ?? '';

                fputcsv($out, [
                    $l->nachname, $l->vorname, $l->lehrberuf ?? '',
                    $total, $avg, $passed, $quote, $last,
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
