<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Services\Benutzer\Startpasswort;
use App\Services\Notifications\AccountMails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Konto eines Lernenden: Passwort zurücksetzen, aktivieren/deaktivieren. */
class KontoController extends VerwaltungController
{
    public function passwort(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('verwalten', $lernender);

        $passwort = Startpasswort::erzeugen();

        $lernender->benutzer->forceFill([
            'passwort_hash' => $passwort,
            'passwort_wechsel_noetig' => true,
        ])->save();

        AccountMails::passwordResetByAdmin($lernender->benutzer);

        return redirect()
            ->to($this->zuRoute($request, 'lernende.show', $lernender_id))
            ->with('success', 'Passwort zurückgesetzt.')
            ->with('startpasswort', $passwort);
    }

    public function aktiv(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('verwalten', $lernender);

        $benutzer = $lernender->benutzer;
        $benutzer->aktiv = ! $benutzer->aktiv;
        $benutzer->save();

        return redirect()
            ->to($this->zuRoute($request, 'lernende.show', $lernender_id))
            ->with('success', $benutzer->aktiv ? 'Konto aktiviert.' : 'Konto deaktiviert.');
    }
}
