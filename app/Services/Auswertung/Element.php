<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

/**
 * Kleinste Einheit mit Zeugnisnote: Fach in einem Semester oder Modul.
 */
final class Element
{
    public const string FACH = 'fach';

    public const string MODUL = 'modul';

    /** @var list<Leistung> */
    public array $leistungen = [];

    /** Gewichteter Schnitt der bewerteten Prüfungen, ungerundet. */
    public ?float $schnitt = null;

    /** Zeugnisnote: Schnitt gerundet nach Kategorie-Regel. */
    public ?float $note = null;

    /** Summe der Gewichte bewerteter Prüfungen. */
    public float $gewichtSumme = 0.0;

    public function __construct(
        public readonly string $schluessel,
        public readonly string $typ,
        public readonly int $kategorieId,
        public readonly ?int $fachId,
        public readonly ?int $modulId,
        public ?int $semesterId,
        public readonly string $label,
        public readonly ?float $zielGewicht = null,
    ) {}

    public function offenGewicht(): ?float
    {
        if ($this->typ !== self::MODUL || ! $this->zielGewicht) {
            return null;
        }

        return max(0.0, $this->zielGewicht - $this->gewichtSumme);
    }

    public function abgeschlossen(): bool
    {
        $offen = $this->offenGewicht();

        return $offen !== null && $offen <= 0.001;
    }

    public function anzahlBewertet(): int
    {
        return count(array_filter($this->leistungen, fn (Leistung $l) => ! $l->istUnbekannt()));
    }

    /** @return list<Leistung> */
    public function unbekannte(): array
    {
        return array_values(array_filter($this->leistungen, fn (Leistung $l) => $l->istUnbekannt()));
    }

    /** @return array<string, mixed> */
    public function toArray(Konfiguration $k, ?int $lernenderId = null): array
    {
        return [
            'schluessel' => $this->schluessel,
            'typ' => $this->typ,
            'label' => $this->label,
            'kategorie_id' => $this->kategorieId,
            'fach_id' => $this->fachId,
            'modul_id' => $this->modulId,
            'semester_id' => $this->semesterId,
            'semester' => $k->semesterName($this->semesterId, $lernenderId),
            'schnitt' => $this->schnitt !== null ? round($this->schnitt, 3) : null,
            'note' => $this->note,
            'gewicht_summe' => $this->gewichtSumme,
            'ziel_gewicht' => $this->zielGewicht,
            'offen_gewicht' => $this->offenGewicht(),
            'abgeschlossen' => $this->abgeschlossen(),
            'anzahl' => $this->anzahlBewertet(),
        ];
    }
}
