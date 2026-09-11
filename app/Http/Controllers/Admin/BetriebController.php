<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use App\Support\Betrieb;
use App\Support\Einstellungen;
use App\Support\Protokoll;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'theme' => Theme::betrieb(),
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
        $validiert = $request->validate(Betrieb::regeln());
        Betrieb::speichern($validiert);
        Protokoll::schreiben(Protokoll::ADMIN_BETRIEB_GEAENDERT, null, ['felder' => array_keys($validiert)]);

        return redirect()->route('admin.operations.edit')->with('success', __('Betrieb gespeichert.'));
    }

    public function themeSpeichern(Request $request): RedirectResponse
    {
        $wert = $request->validate(['theme' => ['required', Rule::in(array_keys(Theme::THEMES))]])['theme'];
        Einstellungen::set(Einstellungen::THEME, $wert);

        return redirect()->route('admin.operations.edit')->with('success', __('Farbthema :name gespeichert.', ['name' => Theme::THEMES[$wert]]));
    }

    public function sicherungErstellen(): RedirectResponse
    {
        try {
            $name = $this->sicherung->erstellen();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.operations.edit')->with('error', __('Sicherung fehlgeschlagen: :grund', ['grund' => mb_substr($e->getMessage(), 0, 200)]));
        }

        Protokoll::schreiben(Protokoll::ADMIN_SICHERUNG_ERSTELLT, null, ['name' => $name]);

        return redirect()->route('admin.operations.edit')->with('success', __('Sicherung :name erstellt.', ['name' => $name]));
    }

    public function sicherungHerunterladen(string $name): BinaryFileResponse
    {
        $pfad = $this->sicherung->pfad($name);
        Protokoll::schreiben(Protokoll::ADMIN_SICHERUNG_HERUNTERGELADEN, null, ['name' => $name]);

        return response()->download($pfad, $name, ['Content-Type' => 'application/zip'])
            ->setPrivate();
    }

    public function sicherungLoeschen(string $name): RedirectResponse
    {
        $this->sicherung->loeschen($name);
        Protokoll::schreiben(Protokoll::ADMIN_SICHERUNG_GELOESCHT, null, ['name' => $name]);

        return redirect()->route('admin.operations.edit')->with('success', __('Sicherung gelöscht.'));
    }

    public function kopieSpeichern(Request $request, SicherungKopie $kopie): RedirectResponse
    {
        try {
            $kopie->speichern($request->validate(SicherungKopie::regeln()));
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors([SicherungKopie::PFAD => $e->getMessage()]);
        }

        return redirect()->route('admin.operations.edit')->with('success', $kopie->aktiv() ? __('Kopie ausser Haus gespeichert.') : __('Kopie ausser Haus ausgeschaltet.'));
    }

    /** «testen» prüft nur Verbindung und Schreibrecht, «kopieren» spiegelt sofort. */
    public function kopieAusfuehren(Request $request, SicherungKopie $kopie): RedirectResponse
    {
        if (! $kopie->aktiv()) {
            return redirect()->route('admin.operations.edit')->with('error', __('Keine Kopie ausser Haus eingerichtet.'));
        }
        $nurTesten = $request->input('aktion') === 'testen';
        try {
            $nurTesten ? $kopie->testen() : $kopie->kopieren();
        } catch (\Throwable $e) {
            return redirect()->route('admin.operations.edit')->with('error', mb_substr($e->getMessage(), 0, 300));
        }

        return redirect()->route('admin.operations.edit')->with('success', $nurTesten ? __('Verbindung zum Ziel funktioniert.') : __('Sicherungen kopiert.'));
    }
}
