<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use InvalidArgumentException;

/**
 * Ein Knoten im Notenbaum. Gruppen mitteln ihre Kinder gewichtet; Blätter holen ihren Wert aus einer
 * Kategorie (Mittel der Zeugnisnoten), aus Fächern (Mittel aller Semesterzeugnisnoten) oder aus einer
 * von Hand erfassten Position (IPA, Schlussprüfung …). Siehe docs/notenlogik.md.
 */
final readonly class Knoten
{
    public const string GRUPPE = 'gruppe';

    public const string KATEGORIE = 'kategorie';

    public const string FAECHER = 'faecher';

    public const string MANUELL = 'manuell';

    public const array TYPEN = [self::GRUPPE, self::KATEGORIE, self::FAECHER, self::MANUELL];

    public const string ALLE = 'alle';

    public const string NUR_MODULE = 'modul';

    public const string NUR_FAECHER = 'fach';

    public const array ELEMENTTYPEN = [self::ALLE, self::NUR_MODULE, self::NUR_FAECHER];

    /**
     * @param  list<int>  $faecher  nur Typ «faecher»
     * @param  list<Knoten>  $kinder  nur Typ «gruppe»
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $typ,
        public float $gewicht = 1.0,
        public ?float $rundung = null,
        public ?float $fallnote = null,
        public ?int $maxUngenuegend = null,
        public ?float $maxMinuspunkte = null,
        public bool $zaehlt = true,
        public ?int $kategorieId = null,
        public string $elementtyp = self::ALLE,
        public array $faecher = [],
        public array $kinder = [],
    ) {
        if (! in_array($typ, self::TYPEN, true)) {
            throw new InvalidArgumentException("Unbekannter Knotentyp «{$typ}».");
        }
        if (! in_array($elementtyp, self::ELEMENTTYPEN, true)) {
            throw new InvalidArgumentException("Unbekannter Elementtyp «{$elementtyp}».");
        }
        if ($gewicht < 0) {
            throw new InvalidArgumentException("Knoten «{$code}»: Gewicht darf nicht negativ sein.");
        }
        if ($typ === self::KATEGORIE && $kategorieId === null) {
            throw new InvalidArgumentException("Knoten «{$code}» braucht eine Kategorie.");
        }
        if ($typ !== self::GRUPPE && $kinder !== []) {
            throw new InvalidArgumentException("Nur Gruppen haben Unterknoten («{$code}»).");
        }
    }

    public function istBlatt(): bool
    {
        return $this->typ !== self::GRUPPE;
    }

    /** Hat der Knoten eine Bestehensregel (Fallnote oder Grenzen über die Unterknoten)? */
    public function hatRegel(): bool
    {
        return $this->fallnote !== null || $this->maxUngenuegend !== null || $this->maxMinuspunkte !== null;
    }
}
