<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Lernender;
use App\Models\Pruefung;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Prüfungstermine aller für den Benutzer sichtbaren Lernenden (Lernender::sichtbarFuer): kommende
 * Termine (heute bis +90 Tage) und kürzlich vergangene ohne Note (letzte 30 Tage), gruppiert nach
 * Woche bzw. Monat. Dazu der persönliche iCal-Abo-Link (CalendarExport).
 */
class PruefungenController extends VerwaltungController
{
    private const int TAGE_VORAUS = 90;

    private const int TAGE_ZURUECK = 30;

    private const array ZEITRAEUME = ['7', '30', 'alle'];

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

        $pruefungen = Pruefung::query()
            ->whereIn('lernender_id', $ids ?: [0])
            ->when($filter['lernender_id'], fn ($q, $id) => $q->where('lernender_id', $id))
            ->where(function ($q) use ($heute, $tageVoraus) {
                $q->whereBetween('datum', [$heute->toDateString(), $heute->addDays($tageVoraus)->toDateString()])
                    ->orWhere(fn ($q2) => $q2
                        ->whereBetween('datum', [$heute->subDays(self::TAGE_ZURUECK)->toDateString(), $heute->subDay()->toDateString()])
                        ->whereNull('note_id'));
            })
            ->with(['fach', 'modul', 'lernender.benutzer', 'note'])
            ->orderBy('datum')->orderBy('uhrzeit')
            ->get();

        return view('verwaltung.pruefungen.index', [
            'gruppen' => $this->gruppieren($pruefungen, $heute),
            'anzahl' => $pruefungen->count(),
            'filter' => $filter,
            'lernendeOptionen' => $lernendeOptionen,
            'bereich' => $this->bereich($request),
        ]);
    }

    /**
     * Prüfungen nach «kürzlich vergangen», dieser/nächster Woche und danach nach Monat gruppiert.
     *
     * @return Collection<int, array{label: string, zeilen: Collection}>
     */
    private function gruppieren(Collection $pruefungen, CarbonImmutable $heute): Collection
    {
        $datum = fn (Pruefung $p) => CarbonImmutable::parse($p->datum->toDateString());
        $mitStatus = fn (Collection $c) => $c->map(fn (Pruefung $p) => ['pruefung' => $p, 'status' => $this->status($p, $heute, $datum($p))])->values();

        $vergangen = $pruefungen->filter(fn (Pruefung $p) => $datum($p)->lt($heute))->values();
        $kommend = $pruefungen->filter(fn (Pruefung $p) => $datum($p)->gte($heute))->values();

        $endeDieseWoche = $heute->endOfWeek();
        $endeNaechsteWoche = $heute->addWeek()->endOfWeek();

        $gruppen = collect();
        if ($vergangen->isNotEmpty()) {
            $gruppen->push(['label' => __('Kürzlich vergangen, ohne Note'), 'zeilen' => $mitStatus($vergangen)]);
        }

        $dieseWoche = $kommend->filter(fn (Pruefung $p) => $datum($p)->lte($endeDieseWoche))->values();
        if ($dieseWoche->isNotEmpty()) {
            $gruppen->push(['label' => __('Diese Woche'), 'zeilen' => $mitStatus($dieseWoche)]);
        }

        $naechsteWoche = $kommend->filter(fn (Pruefung $p) => $datum($p)->gt($endeDieseWoche) && $datum($p)->lte($endeNaechsteWoche))->values();
        if ($naechsteWoche->isNotEmpty()) {
            $gruppen->push(['label' => __('Nächste Woche · KW :kw', ['kw' => $heute->addWeek()->isoWeek()]), 'zeilen' => $mitStatus($naechsteWoche)]);
        }

        $spaeter = $kommend->filter(fn (Pruefung $p) => $datum($p)->gt($endeNaechsteWoche))->groupBy(fn (Pruefung $p) => $datum($p)->format('Y-m'));
        foreach ($spaeter as $monatSchluessel => $zeilen) {
            $gruppen->push(['label' => Format::date(CarbonImmutable::parse($monatSchluessel.'-01'), 'monat_jahr'), 'zeilen' => $mitStatus($zeilen->values())]);
        }

        return $gruppen;
    }

    /** @return array{label: string, klasse: string} */
    private function status(Pruefung $p, CarbonImmutable $heute, CarbonImmutable $datum): array
    {
        if ($p->abgesagt_am) {
            return ['label' => __('Abgesagt'), 'klasse' => 'bg-surface-2 text-muted'];
        }
        if ($p->note) {
            return ['label' => __('Note erfasst'), 'klasse' => 'bg-surface-2 text-text'];
        }

        return $datum->lt($heute)
            ? ['label' => __('Note fehlt'), 'klasse' => 'bg-note-knapp/14 text-note-knapp']
            : ['label' => __('Offen'), 'klasse' => 'bg-surface-2 text-muted'];
    }
}
