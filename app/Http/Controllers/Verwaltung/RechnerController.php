<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Services\Auswertung\Rechner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Noten-Rechner für einen sichtbaren Lernenden (Admin, Berufsbildner); ohne Ziele speichern. */
class RechnerController extends VerwaltungController
{
    public function __construct(private readonly Rechner $rechner) {}

    public function index(Request $request, int $lernender_id): View
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);

        return view('rechner.index', [
            'daten' => $this->rechner->seite($lernender, mitZielen: true),
            'berechnenUrl' => $this->zuRoute($request, 'learners.calculator.calculate', $lernender_id),
            'zielUrl' => null,
            'start' => $request->only(['ziel', 'zielwert']),
            'lernender' => $lernender,
            'zurueck' => $this->zuRoute($request, 'learners.show', $lernender_id),
        ]);
    }

    public function berechnen(Request $request, int $lernender_id): JsonResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);

        return response()->json($this->rechner->berechne($lernender, $request->validate(Rechner::regeln())));
    }
}
