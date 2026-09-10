<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Services\Auswertung\Rechner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Noten-Rechner des Lernenden; Lernender immer aus der Session. */
class RechnerController extends Controller
{
    public function __construct(private readonly Rechner $rechner) {}

    public function index(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);

        return view('rechner.index', [
            'daten' => $this->rechner->seite($lernender, mitZielen: true),
            'berechnenUrl' => route('lernender.noten.rechner.berechnen'),
            'zielUrl' => route('lernender.ziele.store'),
            'start' => $request->only(['ziel', 'zielwert']),
            'lernender' => null,
            'zurueck' => route('lernender.noten.index'),
        ]);
    }

    public function berechnen(Request $request): JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);

        return response()->json($this->rechner->berechne($lernender, $request->validate(Rechner::regeln())));
    }
}
