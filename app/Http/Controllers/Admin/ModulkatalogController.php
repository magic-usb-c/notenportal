<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\DateiNichtGespeichert;
use App\Http\Controllers\Controller;
use App\Services\Import\Katalogimport;
use App\Support\Protokoll;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Modulkatalog über die Oberfläche einlesen: Ernte hochladen → Vorschau prüfen → übernehmen.
 * Damit braucht eine frische Installation für die Stammdaten keine Kommandozeile mehr.
 *
 * Die Regeln stehen in App\Services\Import\Katalogimport – dieselben, die `notenportal:modulkatalog`
 * nutzt. Die hochgeladene Ernte bleibt als Datei liegen (storage/app/private/modulkatalog, nie
 * öffentlich), in der Session stehen nur die Kennzahlen: eine Detail-Ernte mit hunderten Modulen
 * samt Handlungszielen gehört in keine Session.
 *
 * Rechtslage und Herkunft der Daten: docs/modulkatalog.md.
 */
class ModulkatalogController extends Controller
{
    private const string SCHLUESSEL = 'modulkatalog.vorschau';

    private const string ORDNER = 'modulkatalog';

    private const string DISK = 'local';

    /** Eine vollständige Detail-Ernte liegt bei rund einem Megabyte; 10 MB sind reichlich Luft. */
    private const int MAX_KB = 10240;

    /** Liegengebliebene Ernten räumt der nächste Upload weg – nach einem Tag ist keine mehr gültig. */
    private const int AUFBEWAHRUNG_STUNDEN = 24;

    public function index(Request $request): View
    {
        return view('admin.stammdaten.module.katalog', [
            'bereit' => Katalogimport::bereit(),
            'vorschau' => $request->session()->get(self::SCHLUESSEL),
        ]);
    }

    public function lesen(Request $request): RedirectResponse
    {
        abort_unless(Katalogimport::bereit(), 409);

        $request->validate([
            'datei' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:json', 'mimetypes:application/json,text/plain'],
            'ohne_berufe' => ['sometimes', 'boolean'],
            'eigene_uebernehmen' => ['sometimes', 'boolean'],
        ], [], ['datei' => __('Datei')]);

        $datei = $request->file('datei');
        $ohneBerufe = $request->boolean('ohne_berufe');
        $eigeneUebernehmen = $request->boolean('eigene_uebernehmen');

        try {
            $plan = (new Katalogimport($ohneBerufe, $eigeneUebernehmen))
                ->planen((string) file_get_contents((string) $datei->getRealPath()));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }

        $this->aufraeumen();
        $pfad = DateiNichtGespeichert::pruefen($datei->storeAs(self::ORDNER, Str::uuid()->toString().'.json', self::DISK), self::ORDNER);

        // Nur Kennzahlen in die Session; die Listen gekürzt, damit auch eine Ernte mit vielen
        // Konflikten die Sitzung nicht aufbläht.
        $request->session()->put(self::SCHLUESSEL, [
            'pfad' => $pfad,
            'name' => mb_substr((string) $datei->getClientOriginalName(), 0, 120),
            'stand' => $plan['stand'],
            'zahlen' => $plan['zahlen'],
            'konflikte' => array_slice($plan['konflikte'], 0, 20, true),
            'berufe_neu' => array_slice($plan['berufe_neu'], 0, 20),
            'fehler' => array_slice($plan['fehler'], 0, 10),
            'ohne_lernort' => $plan['lernort'] === null,
            'ohne_berufe' => $ohneBerufe,
            'eigene_uebernehmen' => $eigeneUebernehmen,
            'token' => Str::random(40),
        ]);

        return redirect()->route('admin.master-data.modules.catalog');
    }

    public function anwenden(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        // Vorschau atomar aus der Session ziehen und an das Einmal-Token binden: ein zweiter Post
        // (Zurück-Knopf, Doppelklick, zweiter Tab) findet keine Vorschau mehr vor und schreibt nichts
        // ein zweites Mal.
        $vorschau = $request->session()->pull(self::SCHLUESSEL);
        $token = is_string($vorschau['token'] ?? null) ? $vorschau['token'] : null;
        if ($vorschau === null || $token === null || ! hash_equals($token, (string) $request->input('token'))) {
            return back()->with('error', __('Diese Vorschau wurde bereits übernommen.'));
        }

        $ablage = Storage::disk(self::DISK);
        if (! $ablage->exists($vorschau['pfad'])) {
            return back()->with('error', __('Die hochgeladene Ernte ist nicht mehr vorhanden. Bitte erneut hochladen.'));
        }

        $import = new Katalogimport((bool) $vorschau['ohne_berufe'], (bool) $vorschau['eigene_uebernehmen']);
        try {
            // Bewusst neu geplant statt den Plan aufzuheben: zwischen Vorschau und Übernahme kann
            // sich die Datenbank geändert haben, und dann gelten andere Konflikte.
            $bericht = $import->anwenden($import->planen((string) $ablage->get($vorschau['pfad'])));
        } catch (QueryException $e) {
            // QueryException erbt von RuntimeException: ohne diesen Zweig stünde die vollständige
            // SQL-Anweisung samt Werten auf dem Bildschirm. Die gehört ins Serverprotokoll, nicht ins GUI.
            Log::warning('Modulkatalog konnte nicht geschrieben werden: '.$e->getMessage());

            return back()->with('error', __('Der Katalog konnte nicht geschrieben werden. Näheres steht im Protokoll des Servers.'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $ablage->delete($vorschau['pfad']);
        }

        Protokoll::schreiben(Protokoll::ADMIN_MODULKATALOG_IMPORTIERT, null, [
            'datei' => $vorschau['name'],
            'module' => $bericht['module'],
            'lehrberufe' => $bericht['berufe'],
            'zuordnungen' => $bericht['zuordnungen'],
        ]);

        return redirect()->route('admin.master-data.modules.index')->with('success', __(
            ':module Module, :berufe Lehrberufe und :zuordnungen Zuordnungen übernommen.',
            ['module' => $bericht['module'], 'berufe' => $bericht['berufe'], 'zuordnungen' => $bericht['zuordnungen']],
        ));
    }

    public function verwerfen(Request $request): RedirectResponse
    {
        $vorschau = $request->session()->pull(self::SCHLUESSEL);
        if (is_string($vorschau['pfad'] ?? null)) {
            Storage::disk(self::DISK)->delete($vorschau['pfad']);
        }

        return redirect()->route('admin.master-data.modules.catalog');
    }

    /** Ernten, die niemand übernommen hat, liegen sonst dauerhaft in der Ablage. */
    private function aufraeumen(): void
    {
        $ablage = Storage::disk(self::DISK);
        $grenze = now()->subHours(self::AUFBEWAHRUNG_STUNDEN)->getTimestamp();

        foreach ($ablage->files(self::ORDNER) as $datei) {
            if ($ablage->lastModified($datei) < $grenze) {
                $ablage->delete($datei);
            }
        }
    }
}
