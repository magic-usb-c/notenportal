<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Lernstand;
use App\Services\Auswertung\LernstandRechner;
use App\Support\Betrieb;
use App\Support\NotenSkala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BerufsbildnerController extends Controller
{
    public function index(Request $request, LernstandRechner $lernstaende)
    {
        $today = now()->toDateString();
        $cutoff = Betrieb::inaktivVor();

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
                'stats' => collect(),
                'grenze' => NotenSkala::genuegend(),
                'frist' => Betrieb::fristInaktivTage(),
            ]);
        }

        $bbIds = $berufsbildner->pluck('berufsbildner_id')->map(fn ($v) => (int) $v)->all();

        // Aktive Lernende je BB
        $lernendeCount = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereIn('bt.berufsbildner_id', $bbIds)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn ($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->groupBy('bt.berufsbildner_id')
            ->select(['bt.berufsbildner_id', DB::raw('COUNT(*) as cnt')])
            ->get()
            ->keyBy('berufsbildner_id');

        // Lernende ohne Noteneintrag innerhalb der Frist (Einstellung «Erinnerung ohne neue Note nach») je BB
        $letzteNote = DB::table('noten')->whereNull('geloescht_am')->groupBy('lernender_id')
            ->select(['lernender_id', DB::raw('MAX(pruefungsdatum) as last_entry')]);
        $ohneNotenCount = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoinSub($letzteNote, 'nn', 'nn.lernender_id', '=', 'l.lernender_id')
            ->whereIn('bt.berufsbildner_id', $bbIds)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn ($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            // Abgeschlossene Lehren erwarten keine Noten mehr – wie der Filter «keine_noten» der Lernendenliste.
            ->where(fn ($q) => $q->whereNull('l.lehrende')->orWhere('l.lehrende', '>=', $today))
            ->where(fn ($q) => $q->whereNull('nn.last_entry')->orWhere('nn.last_entry', '<', $cutoff))
            ->groupBy('bt.berufsbildner_id')
            ->select(['bt.berufsbildner_id', DB::raw('COUNT(*) as cnt')])
            ->get()
            ->keyBy('berufsbildner_id');

        // Lernende pro BB mit ungenügendem Gesamtschnitt – dieselbe Kennzahl wie der Filter «tief_avg» der Lernendenliste
        $betreut = DB::table('betreuungen as bt')
            ->join('lernende as l', 'l.lernender_id', '=', 'bt.lernender_id')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->whereIn('bt.berufsbildner_id', $bbIds)
            ->where('bt.gueltig_von', '<=', $today)
            ->where(fn ($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $today))
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bt.berufsbildner_id', 'l.lernender_id'])
            ->get();
        $staende = $lernstaende->fuer($betreut->pluck('lernender_id')->map(fn ($v) => (int) $v)->unique()->values()->all());
        $grenze = NotenSkala::genuegend();
        $tiefAvgCount = $betreut
            ->groupBy('berufsbildner_id')
            ->map(fn ($rows) => $rows->filter(function ($r) use ($staende, $grenze) {
                $stand = $staende[(int) $r->lernender_id];
                $avg = $stand->auswertung->gesamtNote;

                return $stand->status !== Lernstand::ABGESCHLOSSEN && $avg !== null && $avg < $grenze;
            })->count());

        $stats = $berufsbildner->map(function ($bb) use ($lernendeCount, $ohneNotenCount, $tiefAvgCount) {
            $bbId = (int) $bb->berufsbildner_id;

            return (object) [
                'berufsbildner_id' => $bbId,
                'lernende' => (int) ($lernendeCount->get($bbId)?->cnt ?? 0),
                'ohne_noten' => (int) ($ohneNotenCount->get($bbId)?->cnt ?? 0),
                'tief_avg' => (int) ($tiefAvgCount->get($bbId) ?? 0),
            ];
        })->keyBy('berufsbildner_id');

        return view('admin.berufsbildner.index', compact('berufsbildner', 'stats', 'grenze') + ['frist' => Betrieb::fristInaktivTage()]);
    }
}
