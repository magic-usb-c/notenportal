<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\Lernender;
use App\Models\Pruefung;
use App\Services\Noten\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Geplante Prüfungen des Lernenden: planen, verschieben, streichen; «Note eintragen» führt ins Notenformular. */
class PruefungenController extends Controller
{
    public function __construct(private readonly NoteService $noteService) {}

    public function index(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $pruefungen = $lernender->pruefungen()->with(['fach', 'modul'])->orderBy('datum')->get();
        $bearbeiten = $request->filled('bearbeiten') ? $pruefungen->firstWhere('pruefung_id', $request->integer('bearbeiten')) : null;

        return view('lernender.pruefungen.index', [
            'anstehend' => $pruefungen->filter(fn (Pruefung $p) => ! $p->datum->isPast() || $p->datum->isToday())->values(),
            'ohneNote' => $pruefungen->filter(fn (Pruefung $p) => $p->datum->isPast() && ! $p->datum->isToday())->values(),
            'bezugOptionen' => $this->noteService->bezugOptionen((int) $lernender->lernender_id),
            'bearbeiten' => $bearbeiten,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->pruefungen()->create($this->validiere($request, $lernender));

        return redirect()->route('learner.exams.index')->with('success', 'Prüfung geplant.');
    }

    public function update(Request $request, int $pruefung_id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->pruefungen()->whereKey($pruefung_id)->firstOrFail()->update($this->validiere($request, $lernender));

        return redirect()->route('learner.exams.index')->with('success', 'Prüfung aktualisiert.');
    }

    public function destroy(Request $request, int $pruefung_id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->pruefungen()->whereKey($pruefung_id)->firstOrFail()->delete();

        return redirect()->route('learner.exams.index')->with('success', 'Prüfung entfernt.');
    }

    /** @return array<string, mixed> */
    private function validiere(Request $request, Lernender $lernender): array
    {
        $daten = $request->validate([
            'bezug' => ['required', 'string', 'regex:/^(fach|modul):\d+$/'],
            'datum' => ['required', 'date'],
            'gewichtung_prozent' => ['required', 'numeric', 'min:0', 'max:100'],
            'titel' => ['nullable', 'string', 'max:150'],
        ], ['bezug.required' => 'Bitte Fach oder Modul wählen.']);

        [$typ, $id] = explode(':', $daten['bezug']);
        try {
            $this->noteService->kategorieFuer((int) $lernender->lernender_id, $typ, (int) $id);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['bezug' => 'Dieses Fach oder Modul ist nicht verfügbar.']);
        }

        return [
            'fach_id' => $typ === 'fach' ? (int) $id : null,
            'modul_id' => $typ === 'modul' ? (int) $id : null,
            'datum' => $daten['datum'],
            'gewichtung_prozent' => (float) $daten['gewichtung_prozent'],
            'titel' => $daten['titel'] ?? null,
        ];
    }
}
