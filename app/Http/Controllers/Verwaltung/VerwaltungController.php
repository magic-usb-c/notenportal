<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Models\Lernender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Basis der Lernenden-Verwaltung. Dieselben Controller bedienen /admin/… und
 * /berufsbildner/…; der Bereich ergibt sich aus dem Routennamen.
 */
abstract class VerwaltungController extends Controller
{
    /** Lernender, den der angemeldete Benutzer sehen darf – sonst 404. */
    protected function sichtbarerLernender(Request $request, int $lernenderId): Lernender
    {
        return Lernender::sichtbarFuer($request->user())->with('benutzer')->findOrFail($lernenderId);
    }

    protected function bereich(Request $request): string
    {
        $bereich = Str::before((string) $request->route()?->getName(), '.');
        abort_unless(in_array($bereich, ['admin', 'berufsbildner'], true), 404);

        return $bereich;
    }

    protected function zuRoute(Request $request, string $name, mixed $parameter = []): string
    {
        return route($this->bereich($request).'.'.$name, $parameter);
    }

    /** Nach Übergabe oder Beenden einer Betreuung ist der Lernende evtl. nicht mehr sichtbar. */
    protected function zurueckZumLernenden(Request $request, int $lernenderId, string $meldung): RedirectResponse
    {
        $sichtbar = Lernender::sichtbarFuer($request->user())->whereKey($lernenderId)->exists();

        return redirect()
            ->to($sichtbar ? $this->zuRoute($request, 'lernende.show', $lernenderId) : $this->zuRoute($request, 'lernende.index'))
            ->with('success', $meldung);
    }
}
