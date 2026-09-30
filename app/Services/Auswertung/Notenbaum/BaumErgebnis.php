<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use InvalidArgumentException;

/**
 * Ausgewerteter Notenbaum eines Lernenden: Werte aller Knoten, Bestehensstatus und Begründung.
 */
final class BaumErgebnis
{
    public const string BESTANDEN = 'bestanden';

    public const string NICHT_BESTANDEN = 'nicht_bestanden';

    /** Noch nicht alle Positionen erfasst; Gründe sind dann Gefährdungen, keine Entscheide. */
    public const string OFFEN = 'offen';

    /**
     * @param  array<string, KnotenErgebnis>  $knoten  nach Code
     * @param  list<Grund>  $gruende
     */
    public function __construct(
        public readonly Baum $baum,
        public readonly array $knoten,
        public readonly string $status,
        public readonly array $gruende,
    ) {}

    public function wurzel(): KnotenErgebnis
    {
        return $this->knoten[$this->baum->wurzel->code];
    }

    public function knoten(string $code): KnotenErgebnis
    {
        return $this->knoten[$code] ?? throw new InvalidArgumentException("Kein Knoten «{$code}» im Baum «{$this->baum->name}».");
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'baum_id' => $this->baum->id,
            'name' => $this->baum->name,
            'bezug' => $this->baum->bezug,
            'status' => $this->status,
            'gruende' => array_map(fn (Grund $g) => $g->toArray(), $this->gruende),
            'wurzel' => $this->wurzel()->toArray(),
        ];
    }
}
