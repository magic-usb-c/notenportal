<?php

declare(strict_types=1);

namespace App\Http\Controllers\Berufsbildner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LernendeController extends Controller
{
    /**
     * Liste der aktuell betreuten Lernenden (für Auswahl/Switcher).
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

        return view('berufsbildner.lernende.index', [
            'lernende' => $lernende,
        ]);
    }
}
