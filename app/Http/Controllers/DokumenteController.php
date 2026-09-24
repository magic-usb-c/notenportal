<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dokument;
use App\Models\Lernender;
use App\Services\Dokumente\Ablage;
use App\Services\Import\ZeugnisAbgleich;
use App\Services\Noten\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dokumente eines Lernenden – für den Lernenden selbst (lernender.dokumente.*) und in der Verwaltung
 * ({bereich}.learners.documents.*, nur sichtbare Lernende). Dateien nie direkt, nur über diese Aktionen.
 */
class DokumenteController extends Controller
{
    public function __construct(private readonly Ablage $ablage, private readonly NoteService $noten) {}

    public function index(Request $request): View
    {
        [$lernender, $bereich] = $this->kontext($request);
        $lernender->loadMissing('benutzer');

        return view('dokumente.index', [
            'lernender' => $lernender,
            'bereich' => $bereich,
            'dokumente' => Dokument::query()->where('lernender_id', $lernender->lernender_id)
                ->with(['semester', 'hochgeladenVon'])->orderByDesc('erstellt_am')->get(),
            'semester' => $this->noten->semestersForLernender((int) $lernender->lernender_id),
            'darfHochladen' => $this->darfAendern($request, $lernender, $bereich),
            'ich' => (int) $request->user()->benutzer_id,
            'r' => fn (string $name, array $p = []) => $this->route($bereich, $lernender, $name, $p),
            'zurueck' => $bereich ? route($bereich.'.learners.show', $lernender->lernender_id) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$lernender, $bereich] = $this->kontext($request);
        abort_unless($this->darfAendern($request, $lernender, $bereich), 403);

        $regeln = Ablage::regeln();
        $regeln['pruefung_id'] = ['nullable', 'integer', Rule::exists('pruefungen', 'pruefung_id')->where('lernender_id', $lernender->lernender_id)];
        $daten = $request->validate($regeln, [], ['datei' => __('Datei'), 'art' => __('Art'), 'semester_id' => __('Semester'), 'titel' => __('Titel')]);
        $pruefungId = $daten['pruefung_id'] ?? null;
        $dokument = $this->ablage->speichern($lernender, $request->file('datei'), $daten, (int) $request->user()->benutzer_id, $pruefungId ? (int) $pruefungId : null);

        if ($pruefungId) {
            return back()->with('success', __('«:titel» angehängt.', ['titel' => $dokument->titel]));
        }

        return redirect($this->route($bereich, $lernender, 'index'))->with('success', __('«:titel» gespeichert.', ['titel' => $dokument->titel]));
    }

    public function show(Request $request): StreamedResponse
    {
        [$lernender] = $this->kontext($request);

        return $this->ablage->ausliefern($this->dokument($request, $lernender), $request->boolean('anzeigen'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        [$lernender, $bereich] = $this->kontext($request);
        $dokument = $this->dokument($request, $lernender);
        $eigenes = (int) $dokument->hochgeladen_von_benutzer_id === (int) $request->user()->benutzer_id;
        abort_unless($bereich ? $this->darfAendern($request, $lernender, $bereich) : $eigenes, 403);

        $this->ablage->loeschen($dokument);

        if ($dokument->pruefung_id) {
            return back()->with('success', __('«:titel» gelöscht.', ['titel' => $dokument->titel]));
        }

        return redirect($this->route($bereich, $lernender, 'index'))->with('success', __('«:titel» gelöscht.', ['titel' => $dokument->titel]));
    }

    public function abgleich(Request $request, ZeugnisAbgleich $abgleich): View
    {
        [$lernender, $bereich] = $this->kontext($request);
        $dokument = $this->dokument($request, $lernender);
        abort_unless($dokument->istPdf(), 404);
        $semesterId = $request->integer('semester_id') ?: $dokument->semester_id;
        $lernender->loadMissing('benutzer');

        return view('dokumente.abgleich', [
            'lernender' => $lernender,
            'bereich' => $bereich,
            'dokument' => $dokument,
            'semesterId' => $semesterId ? (int) $semesterId : null,
            'semester' => $this->noten->semestersForLernender((int) $lernender->lernender_id),
            'ergebnis' => $abgleich->pruefen($this->ablage->pfad($dokument), (int) $lernender->lernender_id, $semesterId ? (int) $semesterId : null),
            'darfUebernehmen' => $this->darfNotenAnlegen($request, $lernender, $bereich),
            'r' => fn (string $name, array $p = []) => $this->route($bereich, $lernender, $name, $p),
        ]);
    }

    public function abgleichUebernehmen(Request $request, ZeugnisAbgleich $abgleich): RedirectResponse
    {
        [$lernender, $bereich] = $this->kontext($request);
        $dokument = $this->dokument($request, $lernender);
        abort_unless($this->darfNotenAnlegen($request, $lernender, $bereich), 403);

        $daten = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
            'zeilen' => ['required', 'array', 'max:100'],
            'zeilen.*.bezug' => ['required', 'regex:/^(fach|modul):\d+$/'],
            'zeilen.*.note' => ['required', 'numeric', 'min:1', 'max:6'],
            'zeilen.*.uebernehmen' => ['nullable', 'boolean'],
        ]);
        $ergebnis = $abgleich->uebernehmen($daten['zeilen'], (int) $daten['semester_id'], (int) $lernender->lernender_id, (int) $request->user()->benutzer_id);

        $zurueck = redirect($this->route($bereich, $lernender, 'reconcile', ['dokument_id' => $dokument->dokument_id, 'semester_id' => $daten['semester_id']]));
        if ($ergebnis['neu'] === 0) {
            return $zurueck->with('error', $ergebnis['fehler'] !== [] ? implode(' · ', array_slice($ergebnis['fehler'], 0, 3)) : __('Keine Zeile ausgewählt.'));
        }

        return $zurueck->with('success', $ergebnis['neu'] === 1 ? __('1 Zeugnisnote übernommen.') : __(':anzahl Zeugnisnoten übernommen.', ['anzahl' => $ergebnis['neu']]));
    }

    private function darfNotenAnlegen(Request $request, Lernender $lernender, ?string $bereich): bool
    {
        return $bereich === null || Gate::forUser($request->user())->allows('noteAnlegen', $lernender);
    }

    /** @return array{0: Lernender, 1: ?string} */
    private function kontext(Request $request): array
    {
        $name = (string) $request->route()?->getName();
        if (str_starts_with($name, 'learner.')) {
            $lernender = $request->user()?->lernender;
            abort_if(! $lernender, 403);

            return [$lernender, null];
        }

        $bereich = Str::before($name, '.');
        abort_unless(in_array($bereich, ['admin', 'trainer'], true), 404);

        return [Lernender::sichtbarFuer($request->user())->findOrFail((int) $request->route('lernender_id')), $bereich];
    }

    private function dokument(Request $request, Lernender $lernender): Dokument
    {
        return Dokument::query()->where('lernender_id', $lernender->lernender_id)->findOrFail((int) $request->route('dokument_id'));
    }

    private function darfAendern(Request $request, Lernender $lernender, ?string $bereich): bool
    {
        return $bereich === null || Gate::forUser($request->user())->allows('update', $lernender);
    }

    private function route(?string $bereich, Lernender $lernender, string $name, array $parameter = []): string
    {
        return $bereich
            ? route($bereich.'.learners.documents.'.$name, ['lernender_id' => $lernender->lernender_id, ...$parameter])
            : route('learner.documents.'.$name, $parameter);
    }
}
