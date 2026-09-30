<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

/**
 * Wert eines Knotens für einen Lernenden: ungerundeter Schnitt, gerundete Note, Vollständigkeit.
 */
final class KnotenErgebnis
{
    /** @var list<KnotenErgebnis> */
    public array $kinder = [];

    public function __construct(
        public readonly Knoten $knoten,
        public ?float $schnitt = null,
        public ?float $note = null,
        public bool $vollstaendig = false,
        public int $anzahl = 0,
    ) {}

    /** Wert, mit dem der Knoten in seine Gruppe eingeht: gerundet, wenn der Knoten rundet. */
    public function massgebend(): ?float
    {
        return $this->note;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->knoten->id,
            'code' => $this->knoten->code,
            'name' => $this->knoten->name,
            'typ' => $this->knoten->typ,
            'gewicht' => $this->knoten->gewicht,
            'rundung' => $this->knoten->rundung,
            'fallnote' => $this->knoten->fallnote,
            'zaehlt' => $this->knoten->zaehlt,
            'schnitt' => $this->schnitt !== null ? round($this->schnitt, 4) : null,
            'note' => $this->note,
            'vollstaendig' => $this->vollstaendig,
            'anzahl' => $this->anzahl,
            'kinder' => array_map(fn (KnotenErgebnis $k) => $k->toArray(), $this->kinder),
        ];
    }
}
