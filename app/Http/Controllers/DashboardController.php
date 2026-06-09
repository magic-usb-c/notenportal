<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Lernender-Dashboard: Kurzstatistik + Einstieg in Noten */
    public function lernender(Request $request)
    {
        $lernender = $request->user()?->lernender;

        if (!$lernender) {
            abort(403);
        }

        $lernenderId = (int) $lernender->lernender_id;
        $today = now()->toDateString();

        // Aktuelles Semester (null wenn keines aktiv ist)
        $currentSemester = Semester::query()
            ->where('start_datum', '<=', $today)
            ->where('end_datum', '>=', $today)
            ->first();

        // Gesamtzahl Noten (nicht gelöscht)
        $noteCount = Note::query()
            ->where('lernender_id', $lernenderId)
            ->count();

        // Durchschnitt im aktuellen Semester (gewichtet; null/leer → 100%)
        $currentAvg = null;
        if ($currentSemester) {
            $noten = Note::query()
                ->where('lernender_id', $lernenderId)
                ->where('semester_id', $currentSemester->semester_id)
                ->get(['note_wert', 'gewichtung_prozent']);

            if ($noten->isNotEmpty()) {
                $wSum = 0.0;
                $sum  = 0.0;
                foreach ($noten as $n) {
                    $w = ($n->gewichtung_prozent === null) ? 100.0 : (float) $n->gewichtung_prozent;
                    $wSum += $w;
                    $sum  += (float) $n->note_wert * $w;
                }
                $currentAvg = $wSum > 0 ? round($sum / $wSum, 2) : null;
            }
        }

        return view('dashboards.lernender', compact(
            'lernender',
            'currentSemester',
            'noteCount',
            'currentAvg',
        ));
    }

    /** Berufsbildner-Dashboard: aktuell betreute Lernende auf einen Blick */
    public function berufsbildner(Request $request)
    {
        $bb = $request->user()?->berufsbildner;

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
                $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today);
            })
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'b.email'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        return view('dashboards.berufsbildner', compact('lernende'));
    }

    /** Admin-Dashboard: Übersicht aller Lernenden + Kennzahlen */
    public function admin(Request $request)
    {
        $lernende = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['l.lernender_id', 'b.vorname', 'b.nachname', 'b.email'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        $noteCount     = Note::query()->count();
        $lernendCount  = $lernende->count();

        return view('dashboards.admin', compact('lernende', 'noteCount', 'lernendCount'));
    }
}
