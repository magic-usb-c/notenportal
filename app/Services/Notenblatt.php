<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lernender;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\NotenQuelle;
use App\Support\Einstellungen;

/**
 * Druckbares Notenblatt: Zeugnisnoten je Semester und Kategorie mit den zugrunde liegenden Prüfungen.
 */
final class Notenblatt
{
    public function __construct(private readonly NotenQuelle $quelle) {}

    /** @return array<string, mixed> */
    public function fuer(Lernender $l): array
    {
        $l->loadMissing(['benutzer', 'lehrberuf']);
        $a = $this->quelle->auswertung((int) $l->lernender_id);
        $k = $a->konfiguration;

        $semester = [];
        foreach ($a->semesterIds() as $sid) {
            $kategorien = [];
            foreach (array_keys($k->kategorien) as $kid) {
                $elemente = array_values(array_filter(
                    $a->elementeDerKategorie($kid),
                    fn (Element $e) => $e->semesterId === $sid && $e->note !== null
                ));
                if ($elemente === []) {
                    continue;
                }
                $kategorien[] = [
                    'name' => $k->kategorieName($kid),
                    'note' => $a->semester($sid, $kid)['note'],
                    'promotion' => $a->promotion($kid, $sid),
                    'elemente' => array_map(fn (Element $e) => [
                        'label' => $e->label,
                        'note' => $e->note,
                        'schnitt' => $e->schnitt,
                        'offen' => $e->offenGewicht(),
                        'pruefungen' => array_values(array_map(
                            fn (Leistung $p) => ['datum' => $p->datum, 'titel' => $p->titel, 'note' => $p->wert, 'gewicht' => $p->gewicht],
                            array_filter($e->leistungen, fn (Leistung $p) => ! $p->istUnbekannt())
                        )),
                    ], $elemente),
                ];
            }
            $semester[] = ['bezeichnung' => $k->semesterName($sid), 'note' => $a->semester($sid)['note'], 'kategorien' => $kategorien];
        }

        $kategorien = [];
        foreach ($a->kategorien as $kid => $kat) {
            if ($kat['note'] !== null) {
                $kategorien[] = ['name' => $k->kategorieName($kid), 'note' => $kat['note']];
            }
        }

        return [
            'name' => $l->benutzer->vorname.' '.$l->benutzer->nachname,
            'lehrberuf' => $l->lehrberuf?->name,
            'lehrbeginn' => $l->lehrbeginn,
            'lehrende' => $l->lehrende,
            'betrieb' => Einstellungen::get(Einstellungen::BETRIEB_NAME, ''),
            'gesamt' => $a->gesamtNote,
            'kategorien' => $kategorien,
            'semester' => $semester,
        ];
    }
}
