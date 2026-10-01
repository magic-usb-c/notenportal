<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Lernender;
use App\Models\Pruefung;
use App\Services\Noten\NoteService;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Prüfungstermine aller für den Benutzer sichtbaren Lernenden (Lernender::sichtbarFuer): kommende
 * Termine (heute bis +90 Tage) und kürzlich vergangene ohne Note (letzte 30 Tage), gruppiert nach
 * Woche bzw. Monat. Dazu der persönliche iCal-Abo-Link (CalendarExport).
 *
 * Abgabetermine (Rückmeldung #14, art=abgabe) kann die Verwaltung hier zusätzlich je Modul/Fach
 * eines sichtbaren Lernenden erfassen/bearbeiten/löschen – dieselbe Sichtbarkeitsprüfung wie beim
 * Lesen (Lernender::sichtbarFuer), keine eigene Policy. Reguläre Prüfungen bleiben Sache des
 * Lernenden (learner.exams.*) und werden hier nicht bearbeitet.
 */
class PruefungenController extends VerwaltungController
{
    private const int TAGE_VORAUS = 90;

    private const int TAGE_ZURUECK = 30;

    private const array ZEITRAEUME = ['7', '30', 'alle'];

    public function __construct(private readonly NoteService $noteService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $heute = CarbonImmutable::today();

        $lernendeOptionen = Lernender::sichtbarFuer($user)->with('benutzer')->get()
            ->sortBy(fn (Lernender $l) => mb_strtolower($l->benutzer->nachname.' '.$l->benutzer->vorname))
            ->values();
        $ids = $lernendeOptionen->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();

        $filter = [
            'lernender_id' => $request->integer('lernender_id') ?: null,
            'zeitraum' => in_array($request->input('zeitraum'), self::ZEITRAEUME, true) ? $request->input('zeitraum') : 'alle',
        ];
        $tageVoraus = match ($filter['zeitraum']) {
            '7' => 7,
            '30' => 30,
            default => self::TAGE_VORAUS,
        };

        // Termine aller sichtbaren Lernenden im Zeitraum: die Liste zeigt die gewählte Person, die Seitenleiste zählt für alle
        $alle = Pruefung::query()
            ->whereIn('lernender_id', $ids ?: [0])
            ->where(function ($q) use ($heute, $tageVoraus) {
                $q->whereBetween('datum', [$heute->toDateString(), $heute->addDays($tageVoraus)->toDateString()])
                    ->orWhere(fn ($q2) => $q2
                        ->whereBetween('datum', [$heute->subDays(self::TAGE_ZURUECK)->toDateString(), $heute->subDay()->toDateString()])
                        ->whereNull('note_id'));
            })
            ->with(['fach', 'modul', 'lernender.benutzer', 'note'])
            ->orderBy('datum')->orderBy('uhrzeit')
            ->get();
        $pruefungen = $filter['lernender_id'] !== null
            ? $alle->where('lernender_id', $filter['lernender_id'])->values()
            : $alle;
        $fehlt = $alle->filter(fn (Pruefung $p) => $this->zustand($p, $heute) === 'fehlt')->countBy('lernender_id');
        $proLernendem = $alle->countBy('lernender_id');
        $lernende = $lernendeOptionen->map(fn (Lernender $l) => [
            'lernender' => $l,
            'anzahl' => $proLernendem->get($l->lernender_id, 0),
            'fehlt' => $fehlt->get($l->lernender_id, 0),
        ]);

        // Abgabetermine (art=abgabe) pflegen: nur möglich, sobald ein einzelner Lernender gefiltert ist
        // (Fach/Modul-Auswahl hängt vom Lehrberuf/Track ab) und erst nach der Migration der art-Spalte.
        $abgabeMoeglich = Pruefung::hatArtSpalte() && $filter['lernender_id'] !== null && in_array($filter['lernender_id'], $ids, true);
        $bearbeiten = $abgabeMoeglich && $request->filled('bearbeiten')
            ? Pruefung::query()->where('lernender_id', $filter['lernender_id'])->where('art', Pruefung::ART_ABGABE)->find($request->integer('bearbeiten'))
            : null;

        return view('verwaltung.pruefungen.index', [
            'gruppen' => $this->gruppieren($pruefungen, $heute),
            'anzahl' => $pruefungen->count(),
            'anzahlAlle' => $alle->count(),
            'fehltAlle' => $fehlt->sum(),
            'filter' => $filter,
            'lernende' => $lernende,
            'bereich' => $this->bereich($request),
            'abgabeMoeglich' => $abgabeMoeglich,
            'bezugOptionen' => $abgabeMoeglich ? $this->noteService->bezugOptionen($filter['lernender_id']) : [],
            'bearbeiten' => $bearbeiten,
        ]);
    }

    /**
     * Prüfungen nach «kürzlich vergangen», dieser/nächster Woche und danach nach Monat gruppiert.
     *
     * @return Collection<int, array{label: string, fehlt: bool, zeilen: Collection}>
     */
    private function gruppieren(Collection $pruefungen, CarbonImmutable $heute): Collection
    {
        $datum = fn (Pruefung $p) => CarbonImmutable::parse($p->datum->toDateString());
        $mitStatus = fn (Collection $c) => $c->map(fn (Pruefung $p) => ['pruefung' => $p, 'datum' => $datum($p), 'zustand' => $this->zustand($p, $heute)])->values();

        $vergangen = $pruefungen->filter(fn (Pruefung $p) => $datum($p)->lt($heute))->values();
        $kommend = $pruefungen->filter(fn (Pruefung $p) => $datum($p)->gte($heute))->values();

        $endeDieseWoche = $heute->endOfWeek();
        $endeNaechsteWoche = $heute->addWeek()->endOfWeek();

        $gruppen = collect();
        if ($vergangen->isNotEmpty()) {
            $gruppen->push(['label' => __('Kürzlich vergangen, ohne Note'), 'fehlt' => true, 'zeilen' => $mitStatus($vergangen)]);
        }

        $dieseWoche = $kommend->filter(fn (Pruefung $p) => $datum($p)->lte($endeDieseWoche))->values();
        if ($dieseWoche->isNotEmpty()) {
            $gruppen->push(['label' => __('Diese Woche'), 'fehlt' => false, 'zeilen' => $mitStatus($dieseWoche)]);
        }

        $naechsteWoche = $kommend->filter(fn (Pruefung $p) => $datum($p)->gt($endeDieseWoche) && $datum($p)->lte($endeNaechsteWoche))->values();
        if ($naechsteWoche->isNotEmpty()) {
            $gruppen->push(['label' => __('Nächste Woche · KW :kw', ['kw' => $heute->addWeek()->isoWeek()]), 'fehlt' => false, 'zeilen' => $mitStatus($naechsteWoche)]);
        }

        $spaeter = $kommend->filter(fn (Pruefung $p) => $datum($p)->gt($endeNaechsteWoche))->groupBy(fn (Pruefung $p) => $datum($p)->format('Y-m'));
        foreach ($spaeter as $monatSchluessel => $zeilen) {
            $gruppen->push(['label' => Format::date(CarbonImmutable::parse($monatSchluessel.'-01'), 'monat_jahr'), 'fehlt' => false, 'zeilen' => $mitStatus($zeilen->values())]);
        }

        return $gruppen;
    }

    /** abgesagt, benotet, fehlt (vergangen ohne Note) oder offen */
    private function zustand(Pruefung $p, CarbonImmutable $heute): string
    {
        return match (true) {
            $p->abgesagt_am !== null => 'abgesagt',
            $p->note !== null => 'benotet',
            CarbonImmutable::parse($p->datum->toDateString())->lt($heute) => 'fehlt',
            default => 'offen',
        };
    }

    /** Neuer Abgabetermin (art=abgabe) für einen sichtbaren Lernenden. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Pruefung::hatArtSpalte(), 404);

        [$lernender, $daten] = $this->validiereAbgabe($request);
        $lernender->pruefungen()->create([...$daten, 'art' => Pruefung::ART_ABGABE, 'quelle' => Pruefung::MANUELL]);

        return redirect($this->zuRoute($request, 'exams.index', ['lernender_id' => $lernender->lernender_id]))
            ->with('success', __('Abgabetermin erfasst.'));
    }

    /** Nur Abgabetermine sind hier editierbar – reguläre Prüfungen bleiben Sache des Lernenden. */
    public function update(Request $request, int $pruefung_id): RedirectResponse
    {
        abort_unless(Pruefung::hatArtSpalte(), 404);

        [$lernender, $daten] = $this->validiereAbgabe($request);
        $pruefung = $lernender->pruefungen()->where('art', Pruefung::ART_ABGABE)->findOrFail($pruefung_id);
        $pruefung->update($daten);

        return redirect($this->zuRoute($request, 'exams.index', ['lernender_id' => $lernender->lernender_id]))
            ->with('success', __('Abgabetermin aktualisiert.'));
    }

    public function destroy(Request $request, int $pruefung_id): RedirectResponse
    {
        abort_unless(Pruefung::hatArtSpalte(), 404);

        $lernender = $this->sichtbarerLernender($request, $request->integer('lernender_id'));
        $lernender->pruefungen()->where('art', Pruefung::ART_ABGABE)->findOrFail($pruefung_id)->delete();

        return redirect($this->zuRoute($request, 'exams.index', ['lernender_id' => $lernender->lernender_id]))
            ->with('success', __('Abgabetermin gelöscht.'));
    }

    /**
     * Sichtbarkeit wie beim Lesen (Lernender::sichtbarFuer), Fach/Modul-Zugehörigkeit wie beim
     * Lernenden selbst (NoteService::kategorieFuer) – keine eigene Prüflogik für Abgabetermine.
     *
     * @return array{0: Lernender, 1: array<string, mixed>}
     */
    private function validiereAbgabe(Request $request): array
    {
        $daten = $request->validate([
            'lernender_id' => ['required', 'integer'],
            'bezug' => ['required', 'string', 'regex:/^(fach|modul):\d+$/'],
            'titel' => ['required', 'string', 'max:150'],
            'datum' => ['required', 'date'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['bezug.required' => __('Bitte Fach oder Modul wählen.')]);

        $lernender = $this->sichtbarerLernender($request, (int) $daten['lernender_id']);
        [$typ, $id] = explode(':', $daten['bezug']);
        try {
            $this->noteService->kategorieFuer((int) $lernender->lernender_id, $typ, (int) $id, CarbonImmutable::parse($daten['datum']));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['bezug' => __('Dieses Fach oder Modul ist nicht verfügbar.')]);
        }

        return [$lernender, [
            'fach_id' => $typ === 'fach' ? (int) $id : null,
            'modul_id' => $typ === 'modul' ? (int) $id : null,
            'titel' => $daten['titel'],
            'datum' => $daten['datum'],
            // Leer heisst volles Gewicht wie der Spaltenstandard (NOT NULL) und die Prüfungen der Lernenden
            'gewichtung_prozent' => $daten['gewichtung_prozent'] ?? 100,
        ]];
    }
}
