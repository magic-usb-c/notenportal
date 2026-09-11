<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lernender;
use App\Services\Dokumente\Ablage;
use App\Services\Import\NotenImport;
use App\Services\Import\TabellenLeser;
use App\Services\Noten\NoteService;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\GradeWatcher;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Notenimport aus Excel, CSV oder PDF: hochladen → Vorschau prüfen und korrigieren → übernehmen.
 * Lernende importieren eigene Noten, in der Verwaltung nur, wer Noten anlegen darf (Admin).
 */
class NotenImportController extends Controller
{
    public function __construct(
        private readonly NotenImport $import,
        private readonly TabellenLeser $leser,
        private readonly NoteService $noten,
        private readonly GradeWatcher $gradeWatcher = new GradeWatcher,
    ) {}

    public function index(Request $request): View
    {
        [$lernender, $bereich] = $this->kontext($request);
        $lernender->loadMissing('benutzer');

        return view('import.index', [
            'lernender' => $lernender,
            'bereich' => $bereich,
            'vorschau' => $request->session()->get($this->schluessel($lernender)),
            'optionen' => $this->noten->bezugOptionen((int) $lernender->lernender_id),
            'r' => fn (string $name) => $this->route($bereich, $lernender, $name),
            'zurueck' => $bereich ? route($bereich.'.learners.grades.index', $lernender->lernender_id) : route('learner.grades.index'),
        ]);
    }

    public function lesen(Request $request): RedirectResponse
    {
        [$lernender, $bereich] = $this->kontext($request);
        $request->validate([
            'datei' => ['required', 'file', 'max:'.Ablage::MAX_KB, 'extensions:xlsx,xls,ods,csv,txt,pdf'],
        ], [], ['datei' => 'Datei']);

        $datei = $request->file('datei');
        try {
            $tabelle = $this->leser->lesen($datei->getRealPath(), $datei->getClientOriginalExtension());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['datei' => 'Die Datei lässt sich nicht lesen.']);
        }

        $vorschau = $this->import->vorschau($tabelle, (int) $lernender->lernender_id);
        if ($vorschau['zeilen'] === []) {
            throw ValidationException::withMessages(['datei' => 'Keine Noten gefunden.']);
        }

        $request->session()->put($this->schluessel($lernender), [...$vorschau, 'datei' => mb_substr($datei->getClientOriginalName(), 0, 120)]);

        return redirect($this->route($bereich, $lernender, 'index'));
    }

    public function uebernehmen(Request $request): RedirectResponse
    {
        [$lernender, $bereich] = $this->kontext($request);
        $request->validate(['zeilen' => ['required', 'json']]);
        $zeilen = json_decode((string) $request->input('zeilen'), true);
        abort_unless(is_array($zeilen) && array_is_list($zeilen) && count($zeilen) <= TabellenLeser::MAX_ZEILEN, 422);

        $vorher = $this->gradeWatcher->schnappschuss((int) $lernender->lernender_id);
        $ergebnis = $this->import->importieren($zeilen, (int) $lernender->lernender_id, (int) $request->user()->benutzer_id);
        if ($ergebnis['neu'] === 0) {
            return back()->with('error', $ergebnis['fehler'] !== [] ? implode(' · ', array_slice($ergebnis['fehler'], 0, 3)) : 'Keine Zeile ausgewählt.');
        }
        $this->gradeWatcher->pruefen((int) $lernender->lernender_id, $vorher);

        // Eigener Import des Lernenden (nicht durch Verwaltung) → aktive Betreuer, eine Sammelmeldung
        if ($bereich === null) {
            $lernender->loadMissing('benutzer');
            foreach (Empfaenger::aktiveBetreuer((int) $lernender->lernender_id) as $betreuer) {
                Notifier::send($betreuer, NotificationCatalog::GRADE_ADDED, GradeAdded::sammel(
                    $lernender, $ergebnis['neu'], route('trainer.learners.show', $lernender->lernender_id)
                ));
            }
        }

        $request->session()->forget($this->schluessel($lernender));
        $ziel = $bereich ? route($bereich.'.learners.grades.index', $lernender->lernender_id) : route('learner.grades.index');
        $antwort = redirect($ziel)->with('success', ($ergebnis['neu'] === 1 ? '1 Note' : $ergebnis['neu'].' Noten').' importiert.');

        return $ergebnis['fehler'] !== []
            ? $antwort->with('error', count($ergebnis['fehler']).' nicht übernommen: '.implode(' · ', array_slice($ergebnis['fehler'], 0, 3)))
            : $antwort;
    }

    public function verwerfen(Request $request): RedirectResponse
    {
        [$lernender, $bereich] = $this->kontext($request);
        $request->session()->forget($this->schluessel($lernender));

        return redirect($this->route($bereich, $lernender, 'index'));
    }

    public function vorlage(Request $request): Response
    {
        [$lernender] = $this->kontext($request);

        return response($this->import->vorlage((int) $lernender->lernender_id), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="notenliste_vorlage.csv"',
        ]);
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
        $lernender = Lernender::sichtbarFuer($request->user())->findOrFail((int) $request->route('lernender_id'));
        Gate::forUser($request->user())->authorize('noteAnlegen', $lernender);

        return [$lernender, $bereich];
    }

    private function schluessel(Lernender $lernender): string
    {
        return 'notenimport.'.$lernender->lernender_id;
    }

    private function route(?string $bereich, Lernender $lernender, string $name): string
    {
        return $bereich
            ? route($bereich.'.learners.grades.import.'.$name, $lernender->lernender_id)
            : route('learner.grades.import.'.$name);
    }
}
