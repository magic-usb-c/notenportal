<?php

declare(strict_types=1);

namespace App\Http\Controllers\Berufsbildner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LernendeController extends Controller
{
    /**
     * Liste der aktuell betreuten Lernenden mit Notenstats.
     *
     * Regeln:
     * - Nur Lernende, die aktuell betreut werden (betreuungen.gueltig_von/bis)
     * - Nur aktive Benutzer, nicht gelöscht
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $bb = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        $today = now()->toDateString();

        $lernende = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->where('bt.berufsbildner_id', $bb->berufsbildner_id)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('bt.gueltig_bis')
                  ->orWhere('bt.gueltig_bis', '>=', $today);
            })
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select([
                'l.lernender_id',
                'b.vorname',
                'b.nachname',
                'b.email',
            ])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        if ($lernende->isEmpty()) {
            return view('berufsbildner.lernende.index', [
                'lernende' => collect(),
                'stats'    => collect(),
            ]);
        }

        $ids = $lernende->pluck('lernender_id')->map(fn($v) => (int)$v)->all();

        // Letztes Eintrags-Datum je Lernender
        $lastEntries = DB::table('noten')
            ->whereIn('lernender_id', $ids)
            ->whereNull('geloescht_am')
            ->groupBy('lernender_id')
            ->select(['lernender_id', DB::raw('MAX(pruefungsdatum) as last_entry')])
            ->get()
            ->keyBy('lernender_id');

        // Semester-Durchschnitt je Lernender (aktuelles Semester, gewichtet)
        $currentSemAvg = DB::table('noten as n')
            ->join('semester as s', 's.semester_id', '=', 'n.semester_id')
            ->whereIn('n.lernender_id', $ids)
            ->whereNull('n.geloescht_am')
            ->where('s.start_datum', '<=', $today)
            ->where('s.end_datum', '>=', $today)
            ->groupBy('n.lernender_id')
            ->select([
                'n.lernender_id',
                DB::raw('ROUND(SUM(n.note_wert * COALESCE(n.gewichtung_prozent, 100)) / NULLIF(SUM(COALESCE(n.gewichtung_prozent, 100)), 0), 2) as avg'),
                DB::raw('COUNT(*) as count'),
            ])
            ->get()
            ->keyBy('lernender_id');

        $stats = $lernende->map(function ($l) use ($lastEntries, $currentSemAvg) {
            $lid       = (int) $l->lernender_id;
            $lastRow   = $lastEntries->get($lid);
            $avgRow    = $currentSemAvg->get($lid);
            $lastEntry = $lastRow ? Carbon::parse($lastRow->last_entry) : null;
            $daysSince = $lastEntry ? (int) $lastEntry->diffInDays(now()) : null;
            $semAvg    = $avgRow ? round((float) $avgRow->avg, 2) : null;

            return (object) [
                'lernender_id' => $lid,
                'lastEntry'    => $lastEntry,
                'daysSince'    => $daysSince,
                'semAvg'       => $semAvg,
                'warningGelb'  => $daysSince === null || $daysSince > 30,
                'warningRot'   => $semAvg !== null && $semAvg < 4.0,
            ];
        })->keyBy('lernender_id');

        return view('berufsbildner.lernende.index', compact('lernende', 'stats'));
    }
}
