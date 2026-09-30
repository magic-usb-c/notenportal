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
        $z = fn (float $x) => number_format($x, 1, ',', '');

        $text = match ($this->regel) {
            self::FALLNOTE => ':name :wert liegt unter :grenze',
            self::UNGENUEGEND => ':name: :anzahl ungenügende Noten, erlaubt sind :max',
            self::MINUSPUNKTE => ':name: :wert Minuspunkte, erlaubt sind :grenze',
        };
        $ersatz = [':name' => $this->name, ':wert' => $z($this->wert), ':grenze' => $z($this->grenze),
            ':anzahl' => (string) (int) $this->wert, ':max' => (string) (int) $this->grenze];

        if (function_exists('app') && app()->bound('translator')) {
            return __($text, array_combine(array_map(fn ($k) => ltrim($k, ':'), array_keys($ersatz)), $ersatz));
        }

        return strtr($text, $ersatz);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'regel' => $this->regel, 'wert' => $this->wert,
            'grenze' => $this->grenze, 'definitiv' => $this->definitiv, 'text' => $this->text()];
    }
}
