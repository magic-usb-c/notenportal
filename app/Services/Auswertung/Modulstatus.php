<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use App\Models\ModulBelegung;
use App\Models\Pruefung;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Modulstatus je Modul und Fach eines Lernenden (Rückmeldung #14): Beginn und Dauer seit Beginn,
 * nächster und letzter Termin (Prüfung oder Abgabetermin aus der Tabelle `pruefungen`), Fortschritt
 * zwischen Beginn und letztem Termin in Prozent, bewerteter Anteil nach Gewichtung (Summe der
 * Gewichte bewerteter Leistungen / Gesamtgewicht) und aktueller Stand (Zeugnisnote/Schnitt).
 *
 * Nutzt den bestehenden Rechenkern (NotenQuelle::auswertung mit geplanten Prüfungen) für Note,
 * Gewichtssummen und Zielgewicht – keine eigene Notenlogik, keine eigene Restdauer-Formatierung
 * (App\Support\Format). status() ist reine Berechnung aus bereits geladenen Werten (unit-testbar
 * ohne DB); fuerLernenden() lädt Termine und Belegungen für den Lernenden in je einem Rutsch.
 */
final class Modulstatus
{
    public function __construct(private readonly NotenQuelle $quelle = new NotenQuelle) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fuerLernenden(int $lernenderId): array
    {
        $auswertung = $this->quelle->auswertung($lernenderId, mitGeplanten: true);
        $k = $auswertung->konfiguration;
        $heute = CarbonImmutable::today();

        $termine = Pruefung::query()
            ->where('lernender_id', $lernenderId)
            ->whereNull('abgesagt_am')
            ->get();

        $termineJeModul = $termine->whereNotNull('modul_id')->groupBy(fn (Pruefung $p) => (int) $p->modul_id);
        $termineJeFach = $termine->whereNotNull('fach_id')->groupBy(fn (Pruefung $p) => (int) $p->fach_id);

        $juengsteBelegungJeModul = ModulBelegung::query()
            ->where('lernender_id', $lernenderId)
            ->get()
            ->groupBy(fn (ModulBelegung $b) => (int) $b->modul_id)
            ->map(fn ($liste) => $liste->sortByDesc('start_datum')->first());

        $out = [];
        foreach ($auswertung->elemente as $element) {
            $istModul = $element->typ === Element::MODUL;

            $termineDesElements = ($istModul ? $termineJeModul[$element->modulId] ?? null : $termineJeFach[$element->fachId] ?? null) ?? collect();
            $rohTermine = $termineDesElements->map(fn (Pruefung $p) => [
                'datum' => $p->datum->toDateString(),
                'titel' => $p->titel,
                'art' => $p->art(),
            ])->all();

            $beginn = $istModul
                ? $juengsteBelegungJeModul->get($element->modulId)?->start_datum?->toDateString()
                : ($element->semesterId !== null ? $k->semester[$element->semesterId]['start'] ?? null : null);

            $out[] = $this->status($element, $rohTermine, $beginn, $heute, $k, $lernenderId);
        }

        usort($out, fn (array $a, array $b) => [$a['typ'], mb_strtolower($a['label'])] <=> [$b['typ'], mb_strtolower($b['label'])]);

        return $out;
    }

    /**
     * @param  list<array{datum: string, titel: ?string, art: string}>  $termine  nicht abgesagte Termine, unsortiert
     * @return array<string, mixed>
     */
    public function status(Element $element, array $termine, ?string $beginn, CarbonImmutable $heute, Konfiguration $k, ?int $lernenderId = null): array
    {
        $aufbereitet = array_map(fn (array $t) => [
            'datum' => CarbonImmutable::parse($t['datum']),
            'titel' => $t['titel'] ?: ($t['art'] === Pruefung::ART_ABGABE ? __('Abgabetermin') : __('Prüfung')),
            'art' => $t['art'],
        ], $termine);
        usort($aufbereitet, fn (array $a, array $b) => $a['datum'] <=> $b['datum']);

        $kuenftig = array_values(array_filter($aufbereitet, fn (array $t) => $t['datum']->gte($heute)));
        $naechster = $kuenftig[0] ?? null;
        $letzter = $aufbereitet === [] ? null : $aufbereitet[count($aufbereitet) - 1];

        $beginnDatum = $beginn !== null ? CarbonImmutable::parse($beginn) : null;

        $fortschritt = null;
        if ($beginnDatum !== null && $letzter !== null) {
            $gesamtTage = $beginnDatum->startOfDay()->diffInDays($letzter['datum']->startOfDay());
            $fortschritt = $gesamtTage > 0
                ? round(max(0.0, min(100.0, $beginnDatum->startOfDay()->diffInDays($heute, false) / $gesamtTage * 100)), 1)
                : ($heute->gte($letzter['datum']) ? 100.0 : 0.0);
        }

        $offenGewicht = array_sum(array_map(fn (Leistung $l) => $l->gewicht, $element->unbekannte()));
        $gesamtGewicht = $element->zielGewicht ?? ($element->gewichtSumme + $offenGewicht);
        $bewerteterAnteil = $gesamtGewicht > 0.0
            ? round(max(0.0, min(100.0, $element->gewichtSumme / $gesamtGewicht * 100)), 1)
            : null;

        return [
            'typ' => $element->typ,
            'label' => $element->label,
            'schluessel' => $element->schluessel,
            'modul_id' => $element->modulId,
            'fach_id' => $element->fachId,
            'semester_id' => $element->semesterId,
            'semester' => $element->semesterId !== null ? $k->semesterName($element->semesterId, $lernenderId) : null,
            'beginn' => $beginnDatum,
            'dauer_seit_beginn' => $beginnDatum !== null ? $this->seit($beginnDatum, $heute) : null,
            'naechster_termin' => $naechster !== null
                ? $naechster + ['restdauer' => Format::restdauer($naechster['datum'])]
                : null,
            'letzter_termin' => $letzter,
            'fortschritt_prozent' => $fortschritt,
            'bewerteter_anteil_prozent' => $bewerteterAnteil,
            'note' => $element->note,
            'schnitt' => $element->schnitt,
        ];
    }

    /** Dauer seit einem vergangenen Datum, gleiche Einheiten-Staffelung wie Format::restdauer(). */
    private function seit(CarbonImmutable $beginn, CarbonImmutable $heute): string
    {
        $tage = (int) $beginn->startOfDay()->diffInDays($heute->startOfDay(), false);

        if ($tage <= 0) {
            return __('seit heute');
        }
        if ($tage > 60) {
            $n = (int) round($tage / 30);

            return $n === 1 ? __('seit 1 Monat') : __('seit :n Monaten', ['n' => $n]);
        }
        if ($tage > 14) {
            $n = (int) round($tage / 7);

            return $n === 1 ? __('seit 1 Woche') : __('seit :n Wochen', ['n' => $n]);
        }

        return $tage === 1 ? __('seit 1 Tag') : __('seit :n Tagen', ['n' => $tage]);
    }
}
