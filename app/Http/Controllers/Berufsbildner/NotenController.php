<?php

declare(strict_types=1);

namespace App\Http\Controllers\Berufsbildner;

use App\Http\Controllers\Controller;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotenController extends Controller
{
    /**
     * Noten eines ausgewählten Lernenden (read-only MVP).
     *
     * Route-Idee:
     *   /berufsbildner/lernende/{lernender_id}/noten
     *
     * Regeln:
     * - Berufsbildner sieht nur Lernende, die er aktuell betreut
     * - lernender_id muss in der betreuten Liste sein, sonst 404
     * - View bekommt Switcher-Liste + Selected-Lernender
     */
    public function index(Request $request, int $lernender_id)
    {
        $user = $request->user();
        $bb = $user?->berufsbildner;

        if (!$bb) {
            abort(403);
        }

        $today = now()->toDateString();

        // 1) Switcher-Liste: aktuell betreute Lernende
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

        // 2) Zugriff: lernender_id muss in Switcher-Liste sein
        $selectedLernender = $lernende->firstWhere('lernender_id', $lernender_id);
        if (!$selectedLernender) {
            abort(404);
        }

        // 3) Noten des ausgewählten Lernenden (mit optionalen Filtern)
        $q = Note::query()
            ->with(['kategorie', 'semester', 'fach', 'modulBelegung.modul', 'gruppe'])
            ->where('lernender_id', $lernender_id)
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id');

        if ($request->filled('kategorie_id')) {
            $q->where('kategorie_id', (int) $request->input('kategorie_id'));
        }

        if ($request->filled('semester_id')) {
            $q->where('semester_id', (int) $request->input('semester_id'));
        }

        $notes = $q->paginate(25)->withQueryString();

        // 4) Stammdaten für Filter
        $kategorien = Kategorie::query()->orderBy('sortierung')->get();
        $semester   = Semester::query()->orderBy('sortierung')->get();

        return view('berufsbildner.noten.index', [
            'notes' => $notes,

            // Switcher
            'lernende' => $lernende,
            'selectedLernender' => $selectedLernender,
            'selectedLernenderId' => $lernender_id,

            // Filter
            'kategorien' => $kategorien,
            'semester' => $semester,
        ]);
    }
}
