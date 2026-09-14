<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Modul;
use App\Models\ModulDokument;
use App\Services\Dokumente\Modulablage;
use App\Services\Noten\NoteService;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module als gemeinsame Stammdaten: jede angemeldete Person sieht alle Module, darf ein fehlendes
 * anlegen und ein bestehendes ergänzen – Handlungsziele, Beschreibung, Verweis, Unterlagen.
 *
 * Es gibt je Modul genau EINEN Datensatz. Was eine Person einträgt, sehen sofort alle; nichts wird
 * je Lernendem kopiert. Die eigenen Noten hängen weiterhin an der eigenen Modulbelegung und bleiben
 * privat – geteilt wird die Beschreibung des Moduls, nie eine Leistung.
 *
 * Eine Ausnahme von «alle dürfen alles»: die Modulnummer ist die Identität des Moduls und entscheidet,
 * an welchem Modul fremde Noten hängen. Sie ändert nur ein Admin.
 */
class ModulController extends Controller
{
    public function index(Request $request): View
    {
        $suche = trim((string) $request->input('suche', ''));

        $module = Modul::query()
            ->select(['modul_id', 'modul_nummer', 'titel', 'version', 'quelle', 'aktiv'])
            ->withCount(['handlungsziele', 'dokumente'])
            ->when($suche !== '', function ($q) use ($suche) {
                $like = '%'.addcslashes($suche, '%_\\').'%';
                $q->where(fn ($w) => $w->where('modul_nummer', 'like', $like)->orWhere('titel', 'like', $like));
            })
            ->orderBy('modul_nummer')
            ->paginate(50)
            ->withQueryString();

        return view('module.index', ['module' => $module, 'suche' => $suche]);
    }

    public function show(int $modul_id): View
    {
        $modul = Modul::query()
            ->with(['handlungsziele', 'lbvElemente', 'dokumente.hochgeladenVon', 'ersteller'])
            ->findOrFail($modul_id);

        return view('module.show', [
            'modul' => $modul,
            'belegt' => $this->istBelegt($modul->modul_id),
        ]);
    }

    /**
     * Ein Modul in die eigene Notenerfassung holen: legt die eigene offene Belegung an. Das Modul
     * selbst bleibt unberührt – es gibt weiterhin genau einen Datensatz, den alle teilen.
     */
    public function belegen(Request $request, int $modul_id, NoteService $noten): RedirectResponse
    {
        $modul = Modul::query()->findOrFail($modul_id);
        $lernenderId = (int) ($request->user()->lernender?->lernender_id ?? 0);
        abort_if($lernenderId === 0, 403);

        $noten->resolveOrCreateOpenModulBelegung($lernenderId, $modul->modul_id, now()->toDateString());

        return back()->with('success', __('Modul hinzugefügt. Du kannst jetzt eigene Noten dazu erfassen.'));
    }

    public function create(): View
    {
        return view('module.form', ['modul' => new Modul, 'ziele' => '']);
    }

    public function store(Request $request): RedirectResponse
    {
        $daten = $request->validate($this->regeln(null));

        $modul = DB::transaction(function () use ($daten, $request) {
            $modul = Modul::create([
                'modul_nummer' => mb_strtoupper(trim($daten['modul_nummer'])),
                'titel' => $daten['titel'],
                'version' => $daten['version'] ?? null,
                'beschreibung' => $daten['beschreibung'] ?? null,
                'link' => $daten['link'] ?? null,
                'ziel_gewicht_summe_default' => 100.00,
                'aktiv' => 1,
                'quelle' => 'benutzer',
                'erstellt_von_benutzer_id' => $request->user()->benutzer_id,
            ]);
            $this->zieleSchreiben($modul, $daten['handlungsziele'] ?? null);

            return $modul;
        });

        Protokoll::schreiben(Protokoll::MODUL_ANGELEGT, $modul, ['nummer' => $modul->modul_nummer]);

        return redirect()->route('modules.show', $modul->modul_id)
            ->with('success', __('Modul angelegt. Alle sehen es ab sofort.'));
    }

    public function edit(int $modul_id): View
    {
        $modul = Modul::query()->with('handlungsziele')->findOrFail($modul_id);

        $ziele = $modul->handlungsziele
            ->map(fn ($z) => trim(($z->nummer ?? '').' '.$z->text))
            ->implode("\n");

        return view('module.form', ['modul' => $modul, 'ziele' => $ziele]);
    }

    public function update(Request $request, int $modul_id): RedirectResponse
    {
        $modul = Modul::query()->findOrFail($modul_id);
        $daten = $request->validate($this->regeln($modul_id));
        $this->standPruefen($request, $modul);

        DB::transaction(function () use ($modul, $daten, $request) {
            $werte = [
                'titel' => $daten['titel'],
                'version' => $daten['version'] ?? null,
                'beschreibung' => $daten['beschreibung'] ?? null,
                'link' => $daten['link'] ?? null,
            ];

            // Die Nummer ist die Identität des Moduls: an ihr hängen fremde Noten. Nur Admins ändern sie.
            if ($this->darfNummerAendern($request)) {
                $werte['modul_nummer'] = mb_strtoupper(trim($daten['modul_nummer']));
            }

            $modul->update($werte);
            $this->zieleSchreiben($modul, $daten['handlungsziele'] ?? null);

            // Auch eine reine Zieländerung dreht den Stand weiter: sonst merkt die nächste
            // offene Bearbeitung nicht, dass sie auf einem überholten Text sitzt.
            $modul->touch();
        });

        Protokoll::schreiben(Protokoll::MODUL_GEAENDERT, $modul, ['nummer' => $modul->modul_nummer]);

        return redirect()->route('modules.show', $modul->modul_id)->with('success', __('Modul aktualisiert.'));
    }

    public function dokumentSpeichern(Request $request, int $modul_id, Modulablage $ablage): RedirectResponse
    {
        $modul = Modul::query()->findOrFail($modul_id);
        $request->validate(Modulablage::regeln(), [], ['datei' => __('Datei')]);

        $dokument = $ablage->speichern(
            $modul,
            $request->file('datei'),
            $request->input('titel'),
            (int) $request->user()->benutzer_id,
        );

        Protokoll::schreiben(Protokoll::MODUL_DOKUMENT_HOCHGELADEN, $modul, [
            'titel' => $dokument->titel,
            'nummer' => $modul->modul_nummer,
        ]);

        return back()->with('success', __('Unterlage hochgeladen. Alle sehen sie ab sofort.'));
    }

    public function dokumentZeigen(Request $request, int $modul_id, int $modul_dokument_id, Modulablage $ablage): StreamedResponse
    {
        $dokument = ModulDokument::query()
            ->with('modul')
            ->where('modul_id', $modul_id)
            ->findOrFail($modul_dokument_id);

        return $ablage->ausliefern($dokument, ! $request->boolean('download'));
    }

    public function dokumentLoeschen(Request $request, int $modul_id, int $modul_dokument_id, Modulablage $ablage): RedirectResponse
    {
        $dokument = ModulDokument::query()->where('modul_id', $modul_id)->findOrFail($modul_dokument_id);

        // Eine Unterlage steht allen zur Verfügung: entfernen darf sie, wer sie beigesteuert hat – oder ein Admin.
        $eigene = (int) $dokument->hochgeladen_von_benutzer_id === (int) $request->user()->benutzer_id;
        abort_unless($eigene || $request->user()->hasRole('Admin'), 403);

        $titel = $dokument->titel;
        $ablage->loeschen($dokument);

        Protokoll::schreiben(Protokoll::MODUL_DOKUMENT_GELOESCHT, null, ['titel' => $titel, 'modul_id' => $modul_id]);

        return back()->with('success', __('Unterlage entfernt.'));
    }

    /** @return array<string, list<mixed>> */
    /**
     * Schutz vor stillem Überschreiben: das Bearbeitungsformular trägt den Stand mit, den es geladen
     * hat. Hat inzwischen jemand anderes gespeichert, bricht das Speichern ab – sonst verschwänden
     * fremde Ziele, weil das Textfeld die Zielzeilen vollständig ersetzt.
     */
    private function standPruefen(Request $request, Modul $modul): void
    {
        $mitgebracht = trim((string) $request->input('stand', ''));

        if ($mitgebracht !== '' && $mitgebracht !== (string) $modul->aktualisiert_am?->getTimestamp()) {
            throw ValidationException::withMessages([
                'handlungsziele' => __('Jemand anderes hat dieses Modul inzwischen geändert. Öffne die Seite neu und trag deine Ergänzung dort ein, damit nichts verloren geht.'),
            ]);
        }
    }

    private function regeln(?int $modulId): array
    {
        return [
            'modul_nummer' => ['required', 'string', 'max:50',
                Rule::unique('module', 'modul_nummer')->ignore($modulId, 'modul_id')],
            'titel' => ['required', 'string', 'max:255'],
            // Katalogversion des Modulbaukastens: nur sie macht den Verweis dorthin möglich.
            'version' => ['nullable', 'string', 'regex:/^\d{1,2}$/'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'handlungsziele' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /** Führt die angemeldete Person dieses Modul bereits selbst? (Nur Lernende haben Belegungen.) */
    private function istBelegt(int $modulId): bool
    {
        $lernenderId = (int) (request()->user()->lernender?->lernender_id ?? 0);

        return $lernenderId > 0 && DB::table('modul_belegungen')
            ->where('lernender_id', $lernenderId)
            ->where('modul_id', $modulId)
            ->whereNull('end_datum')
            ->exists();
    }

    private function darfNummerAendern(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('Admin');
    }

    /**
     * Handlungsziele stehen als Liste im Formular, eine Zeile je Ziel: eine führende Nummer wird
     * übernommen, sonst zählt die Reihenfolge. Ein leeres Feld lässt die bestehenden Ziele in Ruhe –
     * sonst würde ein Tippfehler im Formular die Arbeit anderer löschen.
     */
    private function zieleSchreiben(Modul $modul, ?string $eingabe): void
    {
        if ($eingabe === null || trim($eingabe) === '') {
            return;
        }

        $modul->handlungsziele()->delete();

        $sortierung = 0;
        foreach (preg_split('/\R/', $eingabe) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '') {
                continue;
            }

            $sortierung++;
            $nummer = (string) $sortierung;
            if (preg_match('/^(\d{1,2}(?:\.\d{1,2})?)[\.\)\s]\s*(.+)$/u', $zeile, $treffer) === 1) {
                $nummer = $treffer[1];
                $zeile = trim($treffer[2]);
            }

            $modul->handlungsziele()->create([
                'nummer' => mb_substr($nummer, 0, 20),
                'text' => mb_substr($zeile, 0, 1000),
                'sortierung' => $sortierung,
            ]);
        }
    }
}
