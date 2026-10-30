<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

/**
 * Warum ein Baum nicht bestanden ist (oder gefährdet): verletzte Fallnote oder Grenze über die Unterknoten.
 */
final readonly class Grund
{
    public const string FALLNOTE = 'fallnote';

    public const string UNGENUEGEND = 'ungenuegend';

    public const string MINUSPUNKTE = 'minuspunkte';

    /**
     * @param  float  $wert  Note (fallnote), Anzahl ungenügender Noten oder Minuspunkte
     * @param  bool  $definitiv  beruht auf einer abgeschlossenen Prüfung, nicht auf einem Zwischenstand
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $regel,
        public float $wert,
        public float $grenze,
        public bool $definitiv = false,
    ) {}

    public function text(): string
    {
        // Wie NotenSkala::format: Punkt als Dezimalzeichen, zwei Stellen ohne Schlussnull – eine Stelle machte aus 3.95 «4.0 liegt unter 4.0».
        // Eine Note unter der Fallnote wird abgeschnitten statt gerundet, damit auch ein ungerundeter Knoten (3.996) nie «4.0 liegt unter 4.0» heisst.
        $z = fn (float $x) => preg_replace('/(\.\d)0$/', '$1', number_format($x, 2, '.', ''));
        $wert = $this->regel === self::FALLNOTE ? floor($this->wert * 100 + 1e-6) / 100 : $this->wert;
        $werte = ['name' => $this->name, 'wert' => $z($wert), 'grenze' => $z($this->grenze),
            'anzahl' => (string) (int) $this->wert, 'max' => (string) (int) $this->grenze];

        // Rechenkern-Tests laufen ohne Laravel-App: dort der deutsche Text mit eingesetzten Werten.
        if (! function_exists('app') || ! app()->bound('translator')) {
            return strtr(match ($this->regel) {
                self::FALLNOTE => ':name :wert liegt unter :grenze',
                self::UNGENUEGEND => ':name: :anzahl ungenügende Noten, erlaubt sind :max',
                self::MINUSPUNKTE => ':name: :wert Minuspunkte, erlaubt sind :grenze',
            }, array_combine(array_map(fn ($k) => ':'.$k, array_keys($werte)), $werte));
        }

        return match ($this->regel) {
            self::FALLNOTE => __(':name :wert liegt unter :grenze', $werte),
            self::UNGENUEGEND => __(':name: :anzahl ungenügende Noten, erlaubt sind :max', $werte),
            self::MINUSPUNKTE => __(':name: :wert Minuspunkte, erlaubt sind :grenze', $werte),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'regel' => $this->regel, 'wert' => $this->wert,
            'grenze' => $this->grenze, 'definitiv' => $this->definitiv, 'text' => $this->text()];
    }
}
