<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use Illuminate\Support\Carbon;

/**
 * Wo steht ein Lernender, und kippt etwas? Grundlage für Klassenübersicht (BB) und Betriebsüberblick (Admin).
 */
final readonly class Lernstand
{
    public const string ROT = 'rot';

    public const string GELB = 'gelb';

    public const string GRUEN = 'gruen';

    /**
     * @param  list<?float>  $verlauf  Semesterschnitt je Semester mit Noten (chronologisch)
     * @param  list<Element>  $ungenuegend  Zeugnisnoten unter der Genügend-Grenze im Bezugssemester
     * @param  list<array{kategorie_id: int, kategorie: string, verletzt: list<string>}>  $promotion  gefährdete Promotionen im Bezugssemester
     * @param  list<array{label: string, vorher: float, nachher: float}>  $einbrueche  Fächer mit Rückgang ≥ 0.5 zum Vorsemester
     * @param  list<string>  $gruende
     */
    public function __construct(
        public int $lernenderId,
        public Auswertung $auswertung,
        public ?int $semesterId,
        public ?float $semesterNote,
        public ?float $vorsemesterNote,
        public array $verlauf,
        public array $ungenuegend,
        public array $promotion,
        public array $einbrueche,
        public ?Carbon $letztePruefung,
        public int $ueberfaellig,
        public string $status,
        public array $gruende,
    ) {}

    public function delta(): ?float
    {
        return $this->semesterNote !== null && $this->vorsemesterNote !== null
            ? round($this->semesterNote - $this->vorsemesterNote, 2)
            : null;
    }

    public function tageOhneNote(): ?int
    {
        return $this->letztePruefung ? (int) $this->letztePruefung->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    public function rang(): int
    {
        return match ($this->status) {
            self::ROT => 0,
            self::GELB => 1,
            default => 2,
        };
    }
}
