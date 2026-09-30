<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use InvalidArgumentException;

/**
 * Gewichteter Notenbaum eines Lehrberufs (QV) oder Bildungsgangs (z. B. Berufsmaturität).
 * Reine Daten ohne DB – geladen von App\Services\Auswertung\Notenbaum\BaumLader.
 */
final class Baum
{
    public const string LEHRBERUF = 'lehrberuf';

    public const string BILDUNGSGANG = 'bildungsgang';

    /** @var array<string, Knoten> */
    private array $nachCode = [];

    /** @var array<int, Knoten> */
    private array $nachId = [];

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Knoten $wurzel,
        public readonly string $bezug = self::LEHRBERUF,
    ) {
        $this->einlesen($wurzel);
    }

    private function einlesen(Knoten $k): void
    {
        if (isset($this->nachCode[$k->code])) {
            throw new InvalidArgumentException("Code «{$k->code}» kommt im Baum «{$this->name}» mehrfach vor.");
        }
        $this->nachCode[$k->code] = $k;
        $this->nachId[$k->id] = $k;
        foreach ($k->kinder as $kind) {
            $this->einlesen($kind);
        }
    }

    /**
     * Baum aus verschachteltem Array (Format der Vorlagen, IDs bereits aufgelöst). Fehlt eine Knoten-ID,
     * wird sie aus der Baum-ID abgeleitet (Tests, Vorschau beim Import).
     *
     * @param  array<string, mixed>  $wurzel
     */
    public static function ausArray(int $id, string $name, array $wurzel, string $bezug = self::LEHRBERUF): self
    {
        $zaehler = 0;

        return new self($id, $name, self::knotenAusArray($wurzel, $id, $zaehler), $bezug);
    }

    /** @param  array<string, mixed>  $d */
    private static function knotenAusArray(array $d, int $baumId, int &$zaehler): Knoten
    {
        $zaehler++;
        $id = isset($d['id']) ? (int) $d['id'] : $baumId * 10000 + $zaehler;
        $kinder = [];
        foreach ($d['kinder'] ?? [] as $kind) {
            $kinder[] = self::knotenAusArray($kind, $baumId, $zaehler);
        }

        return new Knoten(
            id: $id,
            code: (string) $d['code'],
            name: (string) ($d['name'] ?? $d['code']),
            typ: (string) ($d['typ'] ?? ($kinder !== [] ? Knoten::GRUPPE : Knoten::MANUELL)),
            gewicht: isset($d['gewicht']) ? (float) $d['gewicht'] : 1.0,
            rundung: isset($d['rundung']) ? (float) $d['rundung'] : null,
            fallnote: isset($d['fallnote']) ? (float) $d['fallnote'] : null,
            maxUngenuegend: isset($d['max_ungenuegend']) ? (int) $d['max_ungenuegend'] : null,
            maxMinuspunkte: isset($d['max_minuspunkte']) ? (float) $d['max_minuspunkte'] : null,
            zaehlt: (bool) ($d['zaehlt'] ?? true),
            kategorieId: isset($d['kategorie_id']) ? (int) $d['kategorie_id'] : null,
            elementtyp: (string) ($d['elementtyp'] ?? Knoten::ALLE),
            faecher: array_values(array_map('intval', $d['faecher'] ?? [])),
            kinder: $kinder,
        );
    }

    public function knoten(string $code): ?Knoten
    {
        return $this->nachCode[$code] ?? null;
    }

    public function knotenId(string $code): ?int
    {
        return $this->nachCode[$code]?->id ?? null;
    }

    public function knotenMitId(int $id): ?Knoten
    {
        return $this->nachId[$id] ?? null;
    }

    /** @return list<Knoten> alle Knoten in Baumreihenfolge */
    public function alle(): array
    {
        return array_values($this->nachCode);
    }

    /** @return list<Knoten> von Hand zu erfassende Positionen */
    public function positionen(): array
    {
        return array_values(array_filter($this->nachCode, fn (Knoten $k) => $k->typ === Knoten::MANUELL));
    }
}
