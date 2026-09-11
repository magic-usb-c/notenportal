<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\Lernender;
use App\Services\Auswertung\Rechner;
use App\Services\Noten\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Noten-Rechner des Lernenden; Lernender immer aus der Session. */
class RechnerController extends Controller
{
    public function __construct(
        private readonly Rechner $rechner,
        private readonly NoteService $noteService,
    ) {}

    public function index(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $daten = $this->rechner->seite($lernender, mitZielen: true);

        return view('rechner.index', [
            'daten' => $daten,
            'berechnenUrl' => route('learner.grades.calculator.calculate'),
            'zielUrl' => route('learner.goals.store'),
            'start' => Rechner::start($request->only(['ziel', 'zielwert']), $daten),
            'lernender' => null,
            'zurueck' => route('learner.grades.index'),
        ]);
    }

    public function berechnen(Request $request): JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);

        return response()->json($this->rechner->berechne($lernender, $request->validate(Rechner::regeln())));
    }

    /**
     * Notenrechner-Drawer der Notenseite (learner.grades.index, ?rechner=1): bis zu 10 hypothetische Noten
     * gleichzeitig gegen die echten Noten gerechnet, ohne Zielgrösse (jede Zeile hat immer einen Wert) –
     * Auswirkung (Kategorie/Semester/Gesamt) und Promotion. Rechnet über dieselbe Engine wie berechnen()/die
     * echte Anzeige (Rechner::berechne → Rechenkern); Leistungen sind reine Wertobjekte, nie Eloquent-Modelle,
     * es wird also nichts in die Datenbank geschrieben oder zurückgerollt.
     */
    public function simulieren(Request $request): JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);

        $validated = $request->validate([
            'zeilen' => ['required', 'array', 'min:1', 'max:10'],
            'zeilen.*.typ' => ['required', 'in:fach,modul'],
            'zeilen.*.fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'zeilen.*.modul_id' => ['nullable', 'integer', 'exists:module,modul_id'],
            'zeilen.*.pruefungsdatum' => ['required', 'date'],
            'zeilen.*.note_wert' => ['required', 'numeric', 'min:1', 'max:6', 'multiple_of:0.05'],
            'zeilen.*.gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $zeilen = [];
        foreach ($validated['zeilen'] as $i => $z) {
            $zeilen[] = $this->zuZeile($lernender, $z, $i);
        }

        $ergebnis = $this->rechner->berechne($lernender, [
            'ziel' => 'gesamt',
            'zielwert' => 4.0,
            'zeilen' => $zeilen,
        ]);

        return response()->json([
            'vergleich' => $ergebnis['vergleich'],
            'promotion' => $ergebnis['promotion'],
        ]);
    }

    /**
     * Eine Zeile des Drawers (typ, fach_id|modul_id, pruefungsdatum, note_wert, gewichtung_prozent – dieselben
     * Felder wie beim echten Erfassen) als Rechner-Zeile (element/gewicht/wert/datum, siehe Rechner::zeile()).
     *
     * @return array{element: string, gewicht: float, wert: float, datum: string}
     */
    private function zuZeile(Lernender $lernender, array $z, int $i): array
    {
        $datum = (string) $z['pruefungsdatum'];

        if ($z['typ'] === 'fach') {
            if (empty($z['fach_id'])) {
                throw ValidationException::withMessages(["zeilen.{$i}.fach_id" => __('Bitte ein Fach wählen.')]);
            }
            $semester = $this->noteService->semesterForDate($datum);
            if (! $semester) {
                throw ValidationException::withMessages(["zeilen.{$i}.pruefungsdatum" => __('Kein Semester gefunden, das dieses Datum abdeckt.')]);
            }
            $element = 'fach:'.((int) $z['fach_id']).'@semester:'.$semester->semester_id;
        } else {
            if (empty($z['modul_id'])) {
                throw ValidationException::withMessages(["zeilen.{$i}.modul_id" => __('Bitte ein Modul wählen.')]);
            }
            $element = 'modul:'.((int) $z['modul_id']);
        }

        $gewicht = isset($z['gewichtung_prozent']) && $z['gewichtung_prozent'] !== null && $z['gewichtung_prozent'] !== ''
            ? (float) $z['gewichtung_prozent']
            : 100.0;

        return ['element' => $element, 'gewicht' => $gewicht, 'wert' => (float) $z['note_wert'], 'datum' => $datum];
    }
}
