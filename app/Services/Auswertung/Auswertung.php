<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

/**
 * Ergebnis des Rechenkerns für einen Lernenden (oder ein Szenario im Rechner).
 * Alle Durchschnitte folgen docs/notenlogik.md.
 */
final class Auswertung
{
    /** @var array<string, Element> */
    public array $elemente = [];

    /** @var array<int, array{schnitt: ?float, note: ?float, elemente: list<string>}> */
    public array $kategorien = [];

    public ?float $gesamtSchnitt = null;

    public ?float $gesamtNote = null;

    /** Gesetzt, wenn die Auswertung genau einen Lernenden betrifft (relative Semesternummern). */
    public ?int $lernenderId = null;

    public function __construct(public readonly Konfiguration $konfiguration) {}

    /** Offizieller (gerundeter) Wert einer Zielgrösse. */
    public function wert(Zielgroesse $z): ?float
    {
        return $this->ergebnis($z)['note'];
    }

    /** @return array{schnitt: ?float, note: ?float} */
    public function ergebnis(Zielgroesse $z): array
    {
        return match ($z->ebene) {
            'gesamt' => ['schnitt' => $this->gesamtSchnitt, 'note' => $this->gesamtNote],
            'kategorie' => $z->semesterId !== null
                ? $this->semester($z->semesterId, $z->id)
                : ['schnitt' => $this->kategorien[$z->id]['schnitt'] ?? null, 'note' => $this->kategorien[$z->id]['note'] ?? null],
            'semester' => $this->semester($z->id),
            'fach' => $z->semesterId !== null
                ? $this->elementErgebnis($this->elemente["f{$z->id}s{$z->semesterId}"] ?? null)
                : $this->fach($z->id),
            'modul' => $this->elementErgebnis($this->elemente["m{$z->id}"] ?? null),
        };
    }

    /**
     * Semesterschnitt: Mittel aller Zeugnisnoten eines Semesters, optional nur einer Kategorie.
     *
     * @return array{schnitt: ?float, note: ?float, elemente: list<Element>}
     */
    public function semester(int $semesterId, ?int $kategorieId = null): array
    {
        $elemente = array_values(array_filter(
            $this->elemente,
            fn (Element $e) => $e->semesterId === $semesterId && $e->note !== null
                && ($kategorieId === null || $e->kategorieId === $kategorieId)
        ));
        $schnitt = Rundung::mittel(array_map(fn (Element $e) => $e->note, $elemente));
        $schritt = $kategorieId !== null ? $this->konfiguration->rundungSchnitt($kategorieId) : $this->konfiguration->rundungGesamt;

        return ['schnitt' => $schnitt, 'note' => Rundung::auf($schnitt, $schritt), 'elemente' => $elemente];
    }

    /**
     * Fach über die Lehrzeit: Mittel seiner Semesterzeugnisnoten.
     *
     * @return array{schnitt: ?float, note: ?float, elemente: list<Element>}
     */
    public function fach(int $fachId): array
    {
        $elemente = array_values(array_filter($this->elemente, fn (Element $e) => $e->fachId === $fachId && $e->note !== null));
        usort($elemente, fn (Element $a, Element $b) => $this->konfiguration->semesterSortierung($a->semesterId) <=> $this->konfiguration->semesterSortierung($b->semesterId));
        $schnitt = Rundung::mittel(array_map(fn (Element $e) => $e->note, $elemente));
        $schritt = $elemente !== [] ? $this->konfiguration->rundungSchnitt($elemente[0]->kategorieId) : 0.1;

        return ['schnitt' => $schnitt, 'note' => Rundung::auf($schnitt, $schritt), 'elemente' => $elemente];
    }

    /** @return array{schnitt: ?float, note: ?float} */
    private function elementErgebnis(?Element $e): array
    {
        return ['schnitt' => $e?->schnitt, 'note' => $e?->note];
    }

    /**
     * Promotion einer Kategorie in einem Semester nach den Kategorie-Regeln; null ohne Regel oder ohne Noten.
     *
     * @return array{erfuellt: bool, schnitt: ?float, ungenuegend: int, minuspunkte: float, verletzt: list<string>}|null
     */
    public function promotion(int $kategorieId, int $semesterId): ?array
    {
        $regel = $this->konfiguration->kategorien[$kategorieId] ?? null;
        if (! $regel || ($regel['promotion_min_schnitt'] === null && $regel['promotion_max_ungenuegend'] === null && $regel['promotion_max_minuspunkte'] === null)) {
            return null;
        }

        $sem = $this->semester($semesterId, $kategorieId);
        if ($sem['elemente'] === []) {
            return null;
        }

        $grenze = $this->konfiguration->genuegend;
        $ungenuegend = array_filter($sem['elemente'], fn (Element $e) => $e->note < $grenze);
        $minuspunkte = round(array_sum(array_map(fn (Element $e) => $grenze - $e->note, $ungenuegend)), 2);

        $verletzt = [];
        if ($regel['promotion_min_schnitt'] !== null && $sem['note'] < $regel['promotion_min_schnitt']) {
            $verletzt[] = 'schnitt';
        }
        if ($regel['promotion_max_ungenuegend'] !== null && count($ungenuegend) > $regel['promotion_max_ungenuegend']) {
            $verletzt[] = 'ungenuegend';
        }
        if ($regel['promotion_max_minuspunkte'] !== null && $minuspunkte > $regel['promotion_max_minuspunkte']) {
            $verletzt[] = 'minuspunkte';
        }

        return [
            'erfuellt' => $verletzt === [],
            'schnitt' => $sem['note'],
            'ungenuegend' => count($ungenuegend),
            'minuspunkte' => $minuspunkte,
            'verletzt' => $verletzt,
        ];
    }

    /** @return list<int> Semester mit mindestens einer Zeugnisnote, chronologisch. */
    public function semesterIds(): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn (Element $e) => $e->note !== null ? $e->semesterId : null,
            $this->elemente
        ), fn ($id) => $id !== null)));
        usort($ids, fn (int $a, int $b) => $this->konfiguration->semesterSortierung($a) <=> $this->konfiguration->semesterSortierung($b));

        return $ids;
    }

    /**
     * Verlauf je Semester (gesamt, eine Kategorie oder ein Fach).
     *
     * @return list<array{semester_id: int, semester: string, note: ?float, schnitt: ?float}>
     */
    public function verlauf(?int $kategorieId = null, ?int $fachId = null): array
    {
        $out = [];
        foreach ($this->semesterIds() as $sid) {
            if ($fachId !== null) {
                $e = $this->elemente["f{$fachId}s{$sid}"] ?? null;
                $r = ['schnitt' => $e?->schnitt, 'note' => $e?->note];
            } else {
                $r = $this->semester($sid, $kategorieId);
            }
            $out[] = ['semester_id' => $sid, 'semester' => $this->konfiguration->semesterName($sid, $this->lernenderId), 'note' => $r['note'], 'schnitt' => $r['schnitt']];
        }

        return $out;
    }

    /** @return list<Element> Elemente einer Kategorie, Module nach Nummer, Fächer nach Name und Semester. */
    public function elementeDerKategorie(int $kategorieId): array
    {
        $liste = array_values(array_filter($this->elemente, fn (Element $e) => $e->kategorieId === $kategorieId));
        usort($liste, fn (Element $a, Element $b) => [$a->label, $this->konfiguration->semesterSortierung($a->semesterId)]
            <=> [$b->label, $this->konfiguration->semesterSortierung($b->semesterId)]);

        return $liste;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $k = $this->konfiguration;
        $kategorien = [];
        foreach ($this->kategorien as $id => $kat) {
            $kategorien[] = [
                'kategorie_id' => $id,
                'name' => $k->kategorieName($id),
                'schnitt' => $kat['schnitt'] !== null ? round($kat['schnitt'], 3) : null,
                'note' => $kat['note'],
                'elemente' => $kat['elemente'],
            ];
        }

        $semester = [];
        foreach ($this->semesterIds() as $sid) {
            $r = $this->semester($sid);
            $proKategorie = [];
            foreach (array_keys($this->kategorien) as $kid) {
                $rk = $this->semester($sid, $kid);
                if ($rk['note'] !== null) {
                    $proKategorie[] = ['kategorie_id' => $kid, 'note' => $rk['note'], 'promotion' => $this->promotion($kid, $sid)];
                }
            }
            $semester[] = ['semester_id' => $sid, 'bezeichnung' => $k->semesterName($sid, $this->lernenderId), 'note' => $r['note'],
                'schnitt' => $r['schnitt'] !== null ? round($r['schnitt'], 3) : null, 'kategorien' => $proKategorie];
        }

        return [
            'gesamt' => ['schnitt' => $this->gesamtSchnitt !== null ? round($this->gesamtSchnitt, 3) : null, 'note' => $this->gesamtNote],
            'kategorien' => $kategorien,
            'semester' => $semester,
            'elemente' => array_map(fn (Element $e) => $e->toArray($k, $this->lernenderId), array_values($this->elemente)),
        ];
    }
}
