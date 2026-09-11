<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use App\Support\Betrieb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BetriebController extends Controller
{
    public function __construct(private readonly Sicherung $sicherung) {}

    public function edit(Request $request, SicherungKopie $kopie): View
    {
        $mailWerte = MailSettings::values();

        return view('admin.betrieb.edit', [
            'werte' => Betrieb::werte(),
            'mailWerte' => $mailWerte,
            'testTo' => $mailWerte[MailSettings::REDIRECT_TO] ?: (string) $request->user()->email,
            'sicherungen' => $this->sicherung->liste(),
            'letzteSicherung' => $this->sicherung->letzte(),
            'sicherungFehler' => $this->sicherung->fehler(),
            'kopie' => $kopie,
            'kopieWerte' => $kopie->werte(),
            // Schlüssel erst anzeigen (und damit anlegen), wenn SSH gewählt ist
            'kopieSchluessel' => $kopie->ziel() === 'ssh' ? $kopie->oeffentlicherSchluessel() : null,
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

    public function kopieSpeichern(Request $request, SicherungKopie $kopie): RedirectResponse
    {
        try {
            $kopie->speichern($request->validate(SicherungKopie::regeln()));
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors([SicherungKopie::PFAD => $e->getMessage()]);
        }

        return redirect()->route('admin.betrieb.edit')->with('success', $kopie->aktiv() ? 'Kopie ausser Haus gespeichert.' : 'Kopie ausser Haus ausgeschaltet.');
    }

    /** «testen» prüft nur Verbindung und Schreibrecht, «kopieren» spiegelt sofort. */
    public function kopieAusfuehren(Request $request, SicherungKopie $kopie): RedirectResponse
    {
        if (! $kopie->aktiv()) {
            return redirect()->route('admin.betrieb.edit')->with('error', 'Keine Kopie ausser Haus eingerichtet.');
        }
        $nurTesten = $request->input('aktion') === 'testen';
        try {
            $nurTesten ? $kopie->testen() : $kopie->kopieren();
        } catch (\Throwable $e) {
            return redirect()->route('admin.betrieb.edit')->with('error', mb_substr($e->getMessage(), 0, 300));
        }

        return redirect()->route('admin.betrieb.edit')->with('success', $nurTesten ? 'Verbindung zum Ziel funktioniert.' : 'Sicherungen kopiert.');
    }
}
