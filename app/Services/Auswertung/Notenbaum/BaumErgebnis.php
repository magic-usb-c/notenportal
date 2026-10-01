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
     * @param  list<Bedingung>  $bedingungen  alle Bestehensregeln mit Stand, von der Wurzel abwärts
     */
    public function __construct(
        public readonly Baum $baum,
        public readonly array $knoten,
        public readonly string $status,
        public readonly array $gruende,
        public readonly array $bedingungen = [],
    ) {}

    public function wurzel(): KnotenErgebnis
    {
        return $this->knoten[$this->baum->wurzel->code];
    }

    public function knoten(string $code): KnotenErgebnis
    {
        return $this->knoten[$code] ?? throw new InvalidArgumentException("Kein Knoten «{$code}» im Baum «{$this->baum->name}».");
    }

    /**
     * Wie viel der Gesamtnote (0 bis 1) schon auf erfassten Werten beruht – gewichtet wie die Rechnung:
     * Teile ohne Gewicht und Gruppen ohne tragende Teile bewegen die Note nicht und zählen hier nicht mit.
     */
    public function erfasst(): float
    {
        $anteil = function (KnotenErgebnis $e) use (&$anteil): float {
            if ($e->knoten->typ !== Knoten::GRUPPE) {
                return $e->note !== null ? 1.0 : 0.0;
            }
            $summe = 0.0;
            $gewichte = 0.0;
            foreach ($e->kinder as $kind) {
                if (! $kind->knoten->zaehlt || $kind->knoten->gewicht <= 0 || $kind->leer) {
                    continue;
                }
                $summe += $kind->knoten->gewicht * $anteil($kind);
                $gewichte += $kind->knoten->gewicht;
            }

            return $gewichte > 0 ? $summe / $gewichte : 0.0;
        };

        return $anteil($this->wurzel());
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
