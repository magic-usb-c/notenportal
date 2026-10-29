<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Notenbaum\Abschluss;
use App\Services\Auswertung\Notenbaum\AbschlussVeraltet;
use App\Support\StatistikAntwort;
use App\Support\StatistikDaten;
use App\Support\StatistikFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Abschluss (QV, Berufsmaturität) des Lernenden; Lernender immer aus der Session. */
class AbschlussController extends Controller
{
    public function __construct(
        private readonly Abschluss $abschluss,
        private readonly StatistikDaten $statistik,
    ) {}

    /** Abschluss; JSON liefert je offene Position «bestanden» oder die nötige Note (S5). */
    public function index(Request $request): Response|JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);

        // Lernenden-ID nur aus der Session; die Seite kennt keine Filter, unbekannte Parameter fallen weg
        $geladen = $this->statistik->lernender((int) $lernender->lernender_id);
        $paket = StatistikAntwort::paket(StatistikFilter::aus($request, []), $this->statistik->positionen($geladen['leistungen'], $geladen['konfiguration']));
        if ($request->wantsJson()) {
            return StatistikAntwort::json($paket);
        }

        return StatistikAntwort::view('abschluss.index', $this->abschluss->seite((int) $lernender->lernender_id) + [
            'speichernUrl' => route('learner.qualification.update'),
            'lernender' => null,
            'statistik' => $paket,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $request->validate(['werte' => ['required', 'array']]);

        try {
            $anzahl = $this->abschluss->speichern((int) $lernender->lernender_id, (array) $request->input('werte'), (int) $request->user()->benutzer_id);
        } catch (AbschlussVeraltet $e) {
            return redirect()->route('learner.qualification.index')->with('error', $e->getMessage());
        }

        return redirect()->route('learner.qualification.index')
            ->with('success', $anzahl > 0 ? __('Abschlussnoten gespeichert.') : __('Keine Änderungen.'));
    }
}
