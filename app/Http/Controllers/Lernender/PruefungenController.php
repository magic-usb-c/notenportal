<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Lernender;
use App\Models\Pruefung;
use App\Services\Calendar\CalendarExport;
use App\Services\Calendar\SchoolNetDescriptionParser;
use App\Services\Noten\NoteService;
use App\Support\Zahl;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Agenda des Lernenden: eigene Prüfungen (planen, bearbeiten, streichen, «Note eintragen» führt ins
 * Notenformular) zusammen mit importierten Schulnetz-Terminen und -Lektionen. Erkannte, aber nicht
 * automatisch zugeordnete Schulnetz-Prüfungen lassen sich hier übernehmen.
 */
class PruefungenController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly SchoolNetDescriptionParser $parser,
    ) {}

    public function index(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $ansicht = $request->query('ansicht') === 'monat' ? 'monat' : 'liste';
        $zeigeLektionen = $request->boolean('lektionen');
        $heute = CarbonImmutable::today();
        $monatParam = (string) $request->query('monat', '');
        $monat = preg_match('/^\d{4}-\d{2}$/', $monatParam)
            ? CarbonImmutable::createFromFormat('Y-m-d', $monatParam.'-01')->startOfMonth()
            : $heute->startOfMonth();

        $pruefungen = $lernender->pruefungen()->with(['fach', 'modul', 'note', 'dokumente'])->orderBy('datum')->orderBy('uhrzeit')->get();
        $termine = CalendarEvent::where('lernender_id', $lernender->lernender_id)->where('kind', CalendarEvent::APPOINTMENT)->orderBy('starts_at')->get();
        $erkannt = CalendarEvent::where('lernender_id', $lernender->lernender_id)->where('kind', CalendarEvent::EXAM)
            ->whereNull('pruefung_id')->orderBy('starts_at')->get();
        $lektionen = $zeigeLektionen
            ? CalendarEvent::where('lernender_id', $lernender->lernender_id)->where('kind', CalendarEvent::LESSON)
                ->whereBetween('starts_at', [$monat->subMonth(), $monat->addMonths(2)])->orderBy('starts_at')->get()
            : new Collection;

        $eintraege = $this->eintraege($pruefungen, $termine, $erkannt, $lektionen, $heute);
        $bearbeiten = $request->filled('bearbeiten') ? $pruefungen->firstWhere('pruefung_id', $request->integer('bearbeiten')) : null;
        $feed = $lernender->calendarFeeds()->first();

        return view('lernender.agenda.index', [
            'ansicht' => $ansicht,
            'zeigeLektionen' => $zeigeLektionen,
            'monat' => $monat,
            'gruppen' => $this->gruppenFuerListe($eintraege, $heute),
            'monatsraster' => $this->monatsraster($eintraege, $monat),
            'bezugOptionen' => $this->noteService->bezugOptionen((int) $lernender->lernender_id),
            'bearbeiten' => $bearbeiten,
            'feed' => $feed,
            'exportToken' => CalendarExport::token($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->pruefungen()->create([...$this->validiere($request, $lernender), 'quelle' => Pruefung::MANUELL]);

        return redirect()->route('learner.exams.index')->with('success', __('Prüfung geplant.'));
    }

    public function update(Request $request, int $pruefung_id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->pruefungen()->whereKey($pruefung_id)->firstOrFail()->update($this->validiere($request, $lernender));

        return redirect()->route('learner.exams.index')->with('success', __('Prüfung aktualisiert.'));
    }

    public function destroy(Request $request, int $pruefung_id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->pruefungen()->whereKey($pruefung_id)->firstOrFail()->delete();

        return redirect()->route('learner.exams.index')->with('success', __('Prüfung entfernt.'));
    }

    /** Vom Schulnetz erkannte, aber nicht automatisch zugeordnete Prüfung manuell übernehmen. */
    public function adopt(Request $request, int $calendar_event_id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $event = CalendarEvent::where('lernender_id', $lernender->lernender_id)->where('kind', CalendarEvent::EXAM)
            ->whereNull('pruefung_id')->findOrFail($calendar_event_id);

        $daten = $request->validate([
            'bezug' => ['required', 'string', 'regex:/^(fach|modul):\d+$/'],
        ], ['bezug.required' => __('Bitte Fach oder Modul wählen.')]);
        [$typ, $id] = explode(':', $daten['bezug']);
        try {
            $this->noteService->kategorieFuer((int) $lernender->lernender_id, $typ, (int) $id);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['bezug' => __('Dieses Fach oder Modul ist nicht verfügbar.')]);
        }

        $info = $this->parser->parse($event->summary, $event->description);
        $pruefung = $lernender->pruefungen()->create([
            'fach_id' => $typ === 'fach' ? (int) $id : null,
            'modul_id' => $typ === 'modul' ? (int) $id : null,
            'titel' => $info['title'] ?? $event->summary,
            'datum' => $event->starts_at->toDateString(),
            'uhrzeit' => $event->all_day ? null : $event->starts_at->format('H:i:s'),
            'dauer_minuten' => $info['duration_minutes'] ?? ($event->ends_at ? max(0, (int) $event->starts_at->diffInMinutes($event->ends_at)) : null),
            'pruefungsart' => $info['exam_type'] ?? null,
            'hilfsmittel' => $info['aids'] ?? null,
            'stoff' => $info['material'] ?? null,
            'raum' => $event->location,
            'gewichtung_prozent' => $info['weight_percent'] ?? 100,
            'quelle' => Pruefung::ICAL,
            'extern_uid' => $event->uid,
        ]);
        $event->update(['pruefung_id' => $pruefung->pruefung_id]);

        return redirect()->route('learner.exams.index')->with('success', __('Prüfung übernommen.'));
    }

    /** @return array<string, mixed> */
    private function validiere(Request $request, Lernender $lernender): array
    {
        $daten = $request->validate([
            'bezug' => ['required', 'string', 'regex:/^(fach|modul):\d+$/'],
            'datum' => ['required', 'date'],
            'uhrzeit' => ['nullable', 'date_format:H:i'],
            'gewichtung_prozent' => ['required', 'numeric', 'min:0', 'max:100'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsart' => ['nullable', 'string', 'max:150'],
            'dauer_minuten' => ['nullable', 'integer', 'min:1', 'max:600'],
            'hilfsmittel' => ['nullable', 'string', 'max:255'],
            'stoff' => ['nullable', 'string', 'max:5000'],
            'notizen' => ['nullable', 'string', 'max:5000'],
        ], ['bezug.required' => __('Bitte Fach oder Modul wählen.')]);

        [$typ, $id] = explode(':', $daten['bezug']);
        try {
            $this->noteService->kategorieFuer((int) $lernender->lernender_id, $typ, (int) $id);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['bezug' => __('Dieses Fach oder Modul ist nicht verfügbar.')]);
        }

        return [
            'fach_id' => $typ === 'fach' ? (int) $id : null,
            'modul_id' => $typ === 'modul' ? (int) $id : null,
            'datum' => $daten['datum'],
            'uhrzeit' => $daten['uhrzeit'] ?? null,
            'gewichtung_prozent' => (float) $daten['gewichtung_prozent'],
            'titel' => $daten['titel'] ?? null,
            'pruefungsart' => $daten['pruefungsart'] ?? null,
            'dauer_minuten' => $daten['dauer_minuten'] ?? null,
            'hilfsmittel' => $daten['hilfsmittel'] ?? null,
            'stoff' => $daten['stoff'] ?? null,
            'notizen' => $daten['notizen'] ?? null,
        ];
    }

    /**
     * Einheitliche Agenda-Einträge aus Prüfungen, Terminen, erkannten Prüfungen und Lektionen.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function eintraege(Collection $pruefungen, Collection $termine, Collection $erkannt, Collection $lektionen, CarbonImmutable $heute): Collection
    {
        $eintraege = collect();

        foreach ($pruefungen as $p) {
            /** @var Pruefung $p */
            $eintraege->push([
                'key' => 'pruefung-'.$p->pruefung_id,
                'datum' => CarbonImmutable::parse($p->datum->toDateString()),
                'zeit' => $p->uhrzeit ? substr((string) $p->uhrzeit, 0, 5) : null,
                'art' => 'pruefung',
                'titel' => trim($p->bezeichnung().($p->titel ? ' – '.$p->titel : '')),
                'nebentext' => Zahl::prozent($p->gewichtung_prozent),
                'ueberfaellig' => $p->istOffen() && $p->abgesagt_am === null && CarbonImmutable::parse($p->datum->toDateString())->lt($heute),
                'abgesagt' => $p->abgesagt_am !== null,
                'pruefung' => $p,
                'event' => null,
            ]);
        }
        foreach ($termine as $e) {
            /** @var CalendarEvent $e */
            $eintraege->push([
                'key' => 'termin-'.$e->id,
                'datum' => CarbonImmutable::parse($e->starts_at->toDateString()),
                'zeit' => $e->all_day ? null : $e->starts_at->format('H:i'),
                'art' => 'termin',
                'titel' => __('Schulnetz: :titel', ['titel' => $e->summary]),
                'nebentext' => __('Termin').($e->location ? ' · '.$e->location : ''),
                'ueberfaellig' => false,
                'abgesagt' => false,
                'pruefung' => null,
                'event' => $e,
            ]);
        }
        foreach ($erkannt as $e) {
            /** @var CalendarEvent $e */
            $eintraege->push([
                'key' => 'erkannt-'.$e->id,
                'datum' => CarbonImmutable::parse($e->starts_at->toDateString()),
                'zeit' => $e->all_day ? null : $e->starts_at->format('H:i'),
                'art' => 'erkannt',
                'titel' => __('Schulnetz: :titel', ['titel' => $e->summary]),
                'nebentext' => __('erkannt, nicht zugeordnet'),
                'ueberfaellig' => false,
                'abgesagt' => false,
                'pruefung' => null,
                'event' => $e,
            ]);
        }
        foreach ($lektionen as $e) {
            /** @var CalendarEvent $e */
            $eintraege->push([
                'key' => 'lektion-'.$e->id,
                'datum' => CarbonImmutable::parse($e->starts_at->toDateString()),
                'zeit' => $e->all_day ? null : $e->starts_at->format('H:i'),
                'art' => 'lektion',
                'titel' => __('Lektion: :titel', ['titel' => $e->summary]),
                'nebentext' => __('Stundenplan').($e->location ? ' · '.$e->location : ''),
                'ueberfaellig' => false,
                'abgesagt' => false,
                'pruefung' => null,
                'event' => $e,
            ]);
        }

        return $eintraege->sortBy([['datum', 'asc'], ['zeit', 'asc']])->values();
    }

    /** @return array{ueberfaellig: Collection, diese_woche: Collection, naechste_woche: array{label: string, eintraege: Collection}, spaeter: Collection} */
    private function gruppenFuerListe(Collection $eintraege, CarbonImmutable $heute): array
    {
        $ueberfaellig = $eintraege->filter(fn ($e) => $e['ueberfaellig'])->values();
        $kuenftig = $eintraege->reject(fn ($e) => $e['ueberfaellig'] || $e['abgesagt'] || $e['datum']->lt($heute))->values();

        $endeDieseWoche = $heute->endOfWeek();
        $endeNaechsteWoche = $heute->addWeek()->endOfWeek();

        return [
            'ueberfaellig' => $ueberfaellig,
            'diese_woche' => $kuenftig->filter(fn ($e) => $e['datum']->lte($endeDieseWoche))->values(),
            'naechste_woche' => [
                'label' => __('Nächste Woche · KW :kw', ['kw' => $heute->addWeek()->isoWeek()]),
                'eintraege' => $kuenftig->filter(fn ($e) => $e['datum']->gt($endeDieseWoche) && $e['datum']->lte($endeNaechsteWoche))->values(),
            ],
            'spaeter' => $kuenftig->filter(fn ($e) => $e['datum']->gt($endeNaechsteWoche))->values(),
        ];
    }

    /** 7-Spalten-Monatsraster (Montag–Sonntag), Zellen mit ihren Einträgen. */
    private function monatsraster(Collection $eintraege, CarbonImmutable $monat): array
    {
        $start = $monat->startOfMonth()->startOfWeek();
        $ende = $monat->endOfMonth()->endOfWeek();
        $tage = [];
        for ($tag = $start; $tag->lte($ende); $tag = $tag->addDay()) {
            $tage[] = [
                'datum' => $tag,
                'imMonat' => $tag->month === $monat->month,
                'heute' => $tag->isToday(),
                'eintraege' => $eintraege->filter(fn ($e) => $e['datum']->isSameDay($tag) && ! $e['abgesagt'])->values(),
            ];
        }

        return $tage;
    }
}
