<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

/**
 * Eine Bestehensregel des Baums mit ihrem aktuellen Stand: Mindestnote eines Knotens oder Grenze für
 * ungenügende Teile bzw. Minuspunkte einer Gruppe. Verletzte Bedingungen stehen zusätzlich als Grund
 * im Ergebnis; hier stehen auch die erfüllten und offenen, damit die Abschluss-Seite alle zeigen kann.
 */
final readonly class Bedingung
{
    public const string ERFUELLT = 'erfuellt';

    public const string VERLETZT = 'verletzt';

    /** Noch kein Wert, an dem sich die Regel prüfen liesse. */
    public const string OFFEN = 'offen';

    /**
     * @param  string  $regel  Grund::FALLNOTE, Grund::UNGENUEGEND oder Grund::MINUSPUNKTE
     * @param  ?float  $wert  Note, Anzahl ungenügender Teile oder Minuspunkte; null, solange nichts erfasst ist
     * @param  bool  $definitiv  beruht auf einer abgeschlossenen Prüfung (von Hand erfasste Position), nicht
     *                           auf einem Zwischenstand – wie Grund::$definitiv
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $regel,
        public ?float $wert,
        public float $grenze,
        public string $stand,
        public bool $definitiv,
    ) {}
}
