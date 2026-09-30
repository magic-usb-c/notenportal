<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Services\Auswertung\Notenbaum\Abschluss;
use App\Services\Auswertung\Notenbaum\AbschlussVeraltet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Abschluss (QV, Berufsmaturität) eines sichtbaren Lernenden – Admin und betreuende Berufsbildner. */
class AbschlussController extends VerwaltungController
{
    public function __construct(private readonly Abschluss $abschluss) {}

    public function index(Request $request, int $lernender_id): View
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);

        return view('abschluss.index', $this->abschluss->seite($lernender_id) + [
            'speichernUrl' => $this->zuRoute($request, 'learners.qualification.update', $lernender_id),
            'lernender' => $lernender,
            'bereich' => $this->bereich($request),
        ]);
    }

    public function update(Request $request, int $lernender_id): RedirectResponse
    {
        $this->sichtbarerLernender($request, $lernender_id);
        $request->validate(['werte' => ['required', 'array']]);

        $zurueck = redirect()->to($this->zuRoute($request, 'learners.qualification', $lernender_id));
        try {
            $anzahl = $this->abschluss->speichern($lernender_id, (array) $request->input('werte'), (int) $request->user()->benutzer_id);
        } catch (AbschlussVeraltet $e) {
            return $zurueck->with('error', $e->getMessage());
        }

        return $zurueck
            ->with('success', $anzahl > 0 ? __('Abschlussnoten gespeichert.') : __('Keine Änderungen.'));
    }
}
