<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LernendeController extends Controller
{
    /**
     * Admin: Liste aller aktiven Lernenden (für Auswahl/Switcher).
     *
     * Regeln:
     * - Nur aktive Benutzer, nicht gelöscht
     */
    public function index(Request $request)
    {
        $lernende = DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
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

        return view('admin.lernende.index', [
            'lernende' => $lernende,
        ]);
    }
}
