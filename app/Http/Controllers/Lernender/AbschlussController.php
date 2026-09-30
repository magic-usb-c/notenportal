<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Notenbaum\Abschluss;
use App\Services\Auswertung\Notenbaum\AbschlussVeraltet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Abschluss (QV, Berufsmaturität) des Lernenden; Lernender immer aus der Session. */
class AbschlussController extends Controller
{
    public function __construct(private readonly Abschluss $abschluss) {}

    public function index(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);

        return view('abschluss.index', $this->abschluss->seite((int) $lernender->lernender_id) + [
            'speichernUrl' => route('learner.qualification.update'),
            'lernender' => null,
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
