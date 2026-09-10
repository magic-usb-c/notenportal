<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Betrieb\Sicherung;
use App\Support\Betrieb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BetriebController extends Controller
{
    public function __construct(private readonly Sicherung $sicherung) {}

    public function edit(): View
    {
        return view('admin.betrieb.edit', [
            'werte' => Betrieb::werte(),
            'sicherungen' => $this->sicherung->liste(),
            'letzteSicherung' => $this->sicherung->letzte(),
            'sicherungFehler' => $this->sicherung->fehler(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Betrieb::speichern($request->validate(Betrieb::regeln()));

        return redirect()->route('admin.betrieb.edit')->with('success', 'Betrieb gespeichert.');
    }

    public function sicherungErstellen(): RedirectResponse
    {
        try {
            $name = $this->sicherung->erstellen();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.betrieb.edit')->with('error', 'Sicherung fehlgeschlagen: '.mb_substr($e->getMessage(), 0, 200));
        }

        return redirect()->route('admin.betrieb.edit')->with('success', 'Sicherung '.$name.' erstellt.');
    }

    public function sicherungHerunterladen(string $name): BinaryFileResponse
    {
        return response()->download($this->sicherung->pfad($name), $name, ['Content-Type' => 'application/zip'])
            ->setPrivate();
    }

    public function sicherungLoeschen(string $name): RedirectResponse
    {
        $this->sicherung->loeschen($name);

        return redirect()->route('admin.betrieb.edit')->with('success', 'Sicherung gelöscht.');
    }
}
