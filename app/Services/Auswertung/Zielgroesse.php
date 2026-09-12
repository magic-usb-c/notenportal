<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use InvalidArgumentException;

/**
 * Was gemessen wird: gesamt, kategorie:3, kategorie:3@semester:12, semester:12, fach:2, fach:2@semester:12, modul:7
 */
final readonly class Zielgroesse
{
    public const array EBENEN = ['gesamt', 'kategorie', 'semester', 'fach', 'modul'];

    public function __construct(
        public string $ebene,
        public ?int $id = null,
        public ?int $semesterId = null,
    ) {
        if (! in_array($ebene, self::EBENEN, true)) {
            throw new InvalidArgumentException("Unbekannte Ebene «{$ebene}».");
        }
        if ($ebene !== 'gesamt' && $id === null) {
            throw new InvalidArgumentException("Ebene «{$ebene}» braucht eine ID.");
        }
    }

    public static function parse(string $text): self
    {
        if ($text === 'gesamt') {
            return new self('gesamt');
        }
        if (! preg_match('/^(kategorie|semester|fach|modul):(\d+)(?:@semester:(\d+))?$/', $text, $m)) {
            throw new InvalidArgumentException("Ungültige Zielgrösse «{$text}».");
        }
        $semester = isset($m[3]) ? (int) $m[3] : null;
        if ($semester !== null && ! in_array($m[1], ['kategorie', 'fach'], true)) {
            throw new InvalidArgumentException("Ungültige Zielgrösse «{$text}».");
        }

        return new self($m[1], (int) $m[2], $semester);
    }

    public function __toString(): string
    {
        if ($this->ebene === 'gesamt') {
            return 'gesamt';
        }

        return $this->ebene.':'.$this->id.($this->semesterId !== null ? '@semester:'.$this->semesterId : '');
    }

    /** $lernenderId: Kontext mit genau einem Lernenden – relative Semesternummer statt neutralem Namen. */
    public function label(Konfiguration $k, ?int $lernenderId = null): string
    {
        $sem = $this->semesterId !== null ? ' · '.$k->semesterName($this->semesterId, $lernenderId) : '';

        return match ($this->ebene) {
            'gesamt' => 'Gesamtschnitt',
            'kategorie' => $k->kategorieName($this->id).$sem,
            'semester' => 'Semesterschnitt · '.$k->semesterName($this->id, $lernenderId),
            'fach' => $k->fachName($this->id).($sem !== '' ? $sem : ' · Lehrzeit'),
            'modul' => $k->modulName($this->id),
        };
    }
}
