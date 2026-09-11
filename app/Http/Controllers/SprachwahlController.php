<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\Einstellungen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seite «Betrieb», Abschnitt Sprache: Standardsprache und ob Benutzer die Sprache selbst wählen. */
class SprachwahlController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            SetLocale::STANDARD => ['required', Rule::in(SetLocale::SPRACHEN)],
            SetLocale::WAHL_AKTIV => ['nullable', 'boolean'],
        ]);

        Einstellungen::set(SetLocale::STANDARD, $daten[SetLocale::STANDARD]);
        Einstellungen::set(SetLocale::WAHL_AKTIV, $request->boolean(SetLocale::WAHL_AKTIV) ? '1' : '0');

        return redirect()->route('admin.operations.edit')->with('success', __('Spracheinstellungen gespeichert.'));
    }
}
