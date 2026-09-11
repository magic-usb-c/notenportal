<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use App\Support\Betrieb;
use App\Support\Betriebslogo;
use App\Support\Einstellungen;
use App\Support\Protokoll;
use App\Support\Sitzung;
use App\Support\Systemhinweis;
use App\Support\Theme;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'hinweisWerte' => [
                'text' => (string) Einstellungen::get(Einstellungen::HINWEIS_TEXT, ''),
                'art' => Einstellungen::get(Einstellungen::HINWEIS_ART, 'info') ?: 'info',
                'zielgruppe' => Einstellungen::get(Einstellungen::HINWEIS_ZIELGRUPPE, 'alle') ?: 'alle',
                'beginn' => self::lokalesDatetime(Einstellungen::get(Einstellungen::HINWEIS_BEGINN)),
                'ende' => self::lokalesDatetime(Einstellungen::get(Einstellungen::HINWEIS_ENDE)),
                'login' => Einstellungen::get(Einstellungen::HINWEIS_LOGIN, '0') === '1',
            ],
            'sitzungStandard' => Sitzung::standard(),
            'sitzungMinuten' => Einstellungen::get(Einstellungen::SITZUNG_MINUTEN),
            'logoVorhanden' => Betriebslogo::vorhanden(),
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

    public function hinweisSpeichern(Request $request): RedirectResponse
    {
        // Knopf «Hinweis entfernen»: leert nur den Text (und die Version), Art/Zielgruppe/Zeitfenster bleiben unangetastet.
        if ($request->boolean('hinweis_entfernen')) {
            Einstellungen::set(Einstellungen::HINWEIS_TEXT, null);
            Einstellungen::set(Einstellungen::HINWEIS_VERSION, null);

            Protokoll::schreiben(Protokoll::ADMIN_SYSTEMHINWEIS_GEAENDERT, null, [
                'art' => Einstellungen::get(Einstellungen::HINWEIS_ART, 'info'),
                'zielgruppe' => Einstellungen::get(Einstellungen::HINWEIS_ZIELGRUPPE, 'alle'),
                'beginn' => Einstellungen::get(Einstellungen::HINWEIS_BEGINN),
                'ende' => Einstellungen::get(Einstellungen::HINWEIS_ENDE),
                'login' => Einstellungen::get(Einstellungen::HINWEIS_LOGIN, '0') === '1',
                'aktiv' => false,
                'text' => null,
            ]);

            return redirect()->route('admin.operations.edit')->with('success', __('Hinweis entfernt.'));
        }

        $endeRegeln = ['nullable', 'date_format:Y-m-d\TH:i'];
        if ($request->filled(Einstellungen::HINWEIS_BEGINN) && $request->filled(Einstellungen::HINWEIS_ENDE)) {
            $endeRegeln[] = 'after:'.Einstellungen::HINWEIS_BEGINN;
        }

        $validiert = $request->validate([
            Einstellungen::HINWEIS_TEXT => ['nullable', 'string', 'max:300'],
            Einstellungen::HINWEIS_ART => ['required', 'in:info,warnung'],
            Einstellungen::HINWEIS_ZIELGRUPPE => ['required', 'in:alle,lernende,berufsbildner,admins'],
            Einstellungen::HINWEIS_BEGINN => ['nullable', 'date_format:Y-m-d\TH:i'],
            Einstellungen::HINWEIS_ENDE => $endeRegeln,
            Einstellungen::HINWEIS_LOGIN => ['nullable', 'boolean'],
        ]);

        $text = Systemhinweis::normalisiert((string) ($validiert[Einstellungen::HINWEIS_TEXT] ?? ''));
        $art = $validiert[Einstellungen::HINWEIS_ART];
        $zielgruppe = $validiert[Einstellungen::HINWEIS_ZIELGRUPPE];
        $login = $request->boolean(Einstellungen::HINWEIS_LOGIN);
        $beginnUtc = self::utcIso($validiert[Einstellungen::HINWEIS_BEGINN] ?? null);
        $endeUtc = self::utcIso($validiert[Einstellungen::HINWEIS_ENDE] ?? null);

        Einstellungen::set(Einstellungen::HINWEIS_TEXT, $text !== '' ? $text : null);
        Einstellungen::set(Einstellungen::HINWEIS_ART, $art);
        Einstellungen::set(Einstellungen::HINWEIS_ZIELGRUPPE, $zielgruppe);
        Einstellungen::set(Einstellungen::HINWEIS_BEGINN, $beginnUtc);
        Einstellungen::set(Einstellungen::HINWEIS_ENDE, $endeUtc);
        Einstellungen::set(Einstellungen::HINWEIS_LOGIN, $login ? '1' : '0');
        Einstellungen::set(Einstellungen::HINWEIS_VERSION, $text !== '' ? Systemhinweis::version($text) : null);

        Protokoll::schreiben(Protokoll::ADMIN_SYSTEMHINWEIS_GEAENDERT, null, [
            'art' => $art,
            'zielgruppe' => $zielgruppe,
            'beginn' => $beginnUtc,
            'ende' => $endeUtc,
            'login' => $login,
            'aktiv' => $text !== '',
            'text' => mb_substr($text, 0, 300),
        ]);

        return redirect()->route('admin.operations.edit')->with('success', __('Systemhinweis gespeichert.'));
    }

    public function logoSpeichern(Request $request): RedirectResponse
    {
        if ($request->boolean('logo_entfernen')) {
            Betriebslogo::entfernen();
            Protokoll::schreiben(Protokoll::ADMIN_LOGO_ENTFERNT);

            return redirect()->route('admin.operations.edit')->with('success', __('Logo entfernt.'));
        }

        $validiert = $request->validate(Betriebslogo::regeln());
        Betriebslogo::speichern($validiert['logo']);
        Protokoll::schreiben(Protokoll::ADMIN_LOGO_GESPEICHERT);

        return redirect()->route('admin.operations.edit')->with('success', __('Logo gespeichert.'));
    }

    public function sitzungSpeichern(Request $request): RedirectResponse
    {
        $validiert = $request->validate([
            Einstellungen::SITZUNG_MINUTEN => ['required', 'integer', 'between:'.Sitzung::MINUTEN_MIN.','.Sitzung::MINUTEN_MAX],
        ]);

        $von = Sitzung::minuten();
        $bis = (int) $validiert[Einstellungen::SITZUNG_MINUTEN];
        Einstellungen::set(Einstellungen::SITZUNG_MINUTEN, (string) $bis);

        Protokoll::schreiben(Protokoll::ADMIN_SITZUNGSDAUER_GEAENDERT, null, [
            'von' => $von,
            'bis' => $bis,
        ]);

        return redirect()->route('admin.operations.edit')->with('success', __('Sitzungsdauer gespeichert.'));
    }

    /** UTC-ISO-8601 (Speicherformat) aus dem lokalen `datetime-local`-Wert (config('app.timezone')). */
    private static function utcIso(?string $lokal): ?string
    {
        if (! $lokal) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $lokal, config('app.timezone'))
            ->utc()
            ->toIso8601String();
    }

    /** Lokaler `datetime-local`-Wert (config('app.timezone')) aus dem gespeicherten UTC-ISO-8601. */
    private static function lokalesDatetime(?string $utcIso): ?string
    {
        if (! $utcIso) {
            return null;
        }

        try {
            return Carbon::parse($utcIso, 'UTC')->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
