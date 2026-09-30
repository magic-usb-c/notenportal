<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

/**
 * Eine Prüfung im Rechenmodell: erfasste Note, geplante Prüfung (wert = null) oder Annahme im Rechner.
 */
final readonly class Leistung
{
    public const string NOTE = 'note';

    public const string GEPLANT = 'geplant';

    public const string ANNAHME = 'annahme';

    public function __construct(
        public int $kategorieId,
        public ?int $fachId,
        public ?int $modulId,
        public ?int $semesterId,
        public ?string $datum,
        public ?float $wert,
        public float $gewicht = 100.0,
        public string $quelle = self::NOTE,
        public ?int $id = null,
        public ?string $titel = null,
        public ?int $knotenId = null,
    ) {}

    /** Von Hand erfasste Position eines Notenbaums (IPA, Schlussprüfung …); wert null = noch offen. */
    public static function position(int $knotenId, ?float $wert, string $quelle = self::NOTE): self
    {
        return new self(0, null, null, null, null, $wert, 100.0, $quelle, knotenId: $knotenId);
    }

    public function istPosition(): bool
    {
        return $this->knotenId !== null;
    }

    public function mitWert(float $wert): self
    {
        return new self($this->kategorieId, $this->fachId, $this->modulId, $this->semesterId, $this->datum,
            $wert, $this->gewicht, $this->quelle, $this->id, $this->titel, $this->knotenId);
    }

    public function istUnbekannt(): bool
    {
        return $this->wert === null;
    }

    public function schluessel(?int $semesterId): ?string
    {
        if ($this->knotenId !== null) {
            return null;
        }
        if ($this->fachId !== null) {
            return $semesterId !== null ? "f{$this->fachId}s{$semesterId}" : null;
        }

        return "m{$this->modulId}";
    }
}
