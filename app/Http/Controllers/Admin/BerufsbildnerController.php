<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BerufsbildnerController extends Controller
{
    public function index(Request $request)
    {
        $today  = now()->toDateString();
        $cutoff = now()->subDays(30)->toDateString();

        $berufsbildner = DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bb.berufsbildner_id', 'b.benutzer_id', 'b.vorname', 'b.nachname', 'b.email', 'b.aktiv'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        if ($berufsbildner->isEmpty()) {
            return view('admin.berufsbildner.index', [
                'berufsbildner' => collect(),
                'stats'         => collect(),
            ]);
        }

        $bbIds = $berufsbildner->pluck('berufsbildner_id')->map(fn($v) => (int) $v)->all();

        // Aktive Lernende je BB
        $lernendeCount = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereIn('bt.berufsbildner_id', $bbIds)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->groupBy('bt.berufsbildner_id')
            ->select(['bt.berufsbildner_id', DB::raw('COUNT(*) as cnt')])
            ->get()
            ->keyBy('berufsbildner_id');

        // Lernende ohne Noteneintrag in letzten 30 Tagen je BB
        $ohneNotenCount = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin(DB::raw(
                '(SELECT lernender_id, MAX(pruefungsdatum) as last_entry
                  FROM noten WHERE geloescht_am IS NULL
                  GROUP BY lernender_id) as nn'
            ), 'nn.lernender_id', '=', 'l.lernender_id')
            ->whereIn('bt.berufsbildner_id', $bbIds)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->where(fn($q) => $q->whereNull('nn.last_entry')->orWhere('nn.last_entry', '<', $cutoff))
            ->groupBy('bt.berufsbildner_id')
            ->select(['bt.berufsbildner_id', DB::raw('COUNT(*) as cnt')])
            ->get()
            ->keyBy('berufsbildner_id');

        // Lernende mit Ø akt. Semester < 4.0 je BB
        $semAvgs = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->whereNull('n.geloescht_am')
            ->where('s.start_datum', '<=', $today)
            ->where('s.end_datum', '>=', $today)
            ->groupBy('n.lernender_id')
            ->select([
                'n.lernender_id',
                DB::raw('SUM(n.note_wert * COALESCE(n.gewichtung_prozent, 100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent, 100)), 0) as avg'),
            ])
            ->get()
            ->keyBy('lernender_id');

        // Lernende pro BB mit tiefem Ø
        $tiefAvgCount = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereIn('bt.berufsbildner_id', $bbIds)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bt.berufsbildner_id', 'l.lernender_id'])
            ->get()
            ->groupBy('berufsbildner_id')
            ->map(function ($rows) use ($semAvgs) {
                return $rows->filter(function ($r) use ($semAvgs) {
                    $avg = $semAvgs->get((int) $r->lernender_id)?->avg;
                    return $avg !== null && (float) $avg < 4.0;
                })->count();
            });

        $stats = $berufsbildner->map(function ($bb) use ($lernendeCount, $ohneNotenCount, $tiefAvgCount) {
            $bbId = (int) $bb->berufsbildner_id;
            return (object) [
                'berufsbildner_id' => $bbId,
                'lernende'         => (int) ($lernendeCount->get($bbId)?->cnt ?? 0),
                'ohne_noten'       => (int) ($ohneNotenCount->get($bbId)?->cnt ?? 0),
                'tief_avg'         => (int) ($tiefAvgCount->get($bbId) ?? 0),
            ];
        })->keyBy('berufsbildner_id');

        return view('admin.berufsbildner.index', compact('berufsbildner', 'stats'));
    }
}
