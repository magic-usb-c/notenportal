<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Rechenkern;
use App\Services\Auswertung\Rundung;
use App\Services\Auswertung\Zielgroesse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RechenkernTest extends TestCase
{
    use AuswertungTestHilfen;

    /** @return array<string, array{float, float, float}> */
    public static function rundungen(): array
    {
        return [
            'viertel auf halb' => [4.25, 0.5, 4.5],
            'knapp darunter' => [4.24, 0.5, 4.0],
            'dreiviertel' => [4.75, 0.5, 5.0],
            'knapp unter dreiviertel' => [4.749, 0.5, 4.5],
            'zehntel' => [4.35, 0.1, 4.4],
            'ungerundet' => [4.3333, 0.0, 4.3333],
            'float-kante' => [4.45, 0.1, 4.5],
        ];
    }

    #[Test]
    #[DataProvider('rundungen')]
    public function rundet_kaufmaennisch(float $wert, float $schritt, float $erwartet): void
    {
        $this->assertEqualsWithDelta($erwartet, Rundung::auf($wert, $schritt), 1e-9);
    }

    #[Test]
    public function element_ist_gewichteter_schnitt_mit_gerundeter_zeugnisnote(): void
    {
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 5.0, 100),
            $this->fach(self::MATHE, 1, 4.0, 50),
        ], $this->konfiguration());

        $e = $a->elemente['f10s1'];
        $this->assertEqualsWithDelta(4.6667, $e->schnitt, 1e-4);
        $this->assertSame(4.5, $e->note);
    }

    #[Test]
    public function kategorie_mittelt_elemente_statt_pruefungen_zu_poolen(): void
    {
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 6.0), $this->fach(self::MATHE, 1, 6.0),
            $this->fach(self::MATHE, 1, 6.0), $this->fach(self::MATHE, 1, 6.0),
            $this->fach(self::DEUTSCH, 1, 4.0),
        ], $this->konfiguration());

        $this->assertSame(5.0, $a->kategorien[self::BMS]['note']);
    }

    #[Test]
    public function gesamtschnitt_gewichtet_kategorien(): void
    {
        $leistungen = [$this->modul(100, 5.0), $this->fach(self::MATHE, 1, 4.0)];

        $gleich = (new Rechenkern)->auswerten($leistungen, $this->konfiguration());
        $this->assertSame(4.5, $gleich->gesamtNote);

        $bmsDreifach = (new Rechenkern)->auswerten($leistungen, $this->konfiguration(gewichtBms: 3.0));
        $this->assertSame(4.3, $bmsDreifach->gesamtNote); // (5 + 3·4) / 4 = 4.25 → 4.3
    }

    #[Test]
    public function modul_zaehlt_im_semester_der_letzten_pruefung_und_kennt_offene_gewichtung(): void
    {
        $a = (new Rechenkern)->auswerten([
            $this->modul(100, 4.0, 30, '2024-10-01'),
            $this->modul(100, 5.0, 30, '2025-03-01'),
        ], $this->konfiguration());

        $e = $a->elemente['m100'];
        $this->assertSame(2, $e->semesterId);
        $this->assertSame(40.0, $e->offenGewicht());
        $this->assertFalse($e->abgeschlossen());
    }

    #[Test]
    public function fach_ueber_die_lehrzeit_mittelt_semesterzeugnisnoten(): void
    {
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 4.5),
            $this->fach(self::MATHE, 2, 5.5),
            $this->fach(self::MATHE, 2, 5.5),
        ], $this->konfiguration());

        $this->assertSame(5.0, $a->wert(Zielgroesse::parse('fach:10')));
        $this->assertSame(5.5, $a->wert(Zielgroesse::parse('fach:10@semester:2')));
        $this->assertSame(
            [4.5, 5.5],
            array_column($a->verlauf(fachId: self::MATHE), 'note')
        );
    }

    #[Test]
    public function promotion_prueft_schnitt_ungenuegende_und_minuspunkte(): void
    {
        $k = $this->konfiguration();
        $knapp = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 3.5), $this->fach(self::DEUTSCH, 1, 3.5), $this->fach(self::ENGLISCH, 1, 5.0),
        ], $k);
        $p = $knapp->promotion(self::BMS, 1);
        $this->assertTrue($p['erfuellt']);
        $this->assertSame(2, $p['ungenuegend']);
        $this->assertSame(1.0, $p['minuspunkte']);

        $gefaehrdet = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 3.5), $this->fach(self::DEUTSCH, 1, 2.5), $this->fach(self::ENGLISCH, 1, 5.0),
        ], $k);
        $this->assertSame(['schnitt'], $gefaehrdet->promotion(self::BMS, 1)['verletzt']);

        $this->assertNull($gefaehrdet->promotion(self::FACH, 1));
    }

    #[Test]
    public function unbekannte_und_gewicht_null_zaehlen_nicht(): void
    {
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 5.0),
            $this->fach(self::MATHE, 1, 1.0, 0),
            $this->fach(self::MATHE, 1, null),
        ], $this->konfiguration());

        $this->assertSame(5.0, $a->elemente['f10s1']->note);
        $this->assertSame(2, $a->elemente['f10s1']->anzahlBewertet());
        $this->assertSame(100.0, $a->elemente['f10s1']->gewichtSumme);
    }

    #[Test]
    public function semesterschnitt_ueber_alle_kategorien(): void
    {
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::MATHE, 1, 4.0),
            $this->modul(100, 5.5, 100, '2024-11-01'),
            $this->fach(self::MATHE, 2, 3.0),
        ], $this->konfiguration());

        $this->assertSame(4.8, $a->wert(Zielgroesse::parse('semester:1'))); // (4.0 + 5.5) / 2 = 4.75
        $this->assertSame([1, 2], $a->semesterIds());
        $this->assertSame(4.0, $a->wert(Zielgroesse::parse('kategorie:3@semester:1')));
    }

    #[Test]
    public function leere_auswertung_liefert_null(): void
    {
        $a = (new Rechenkern)->auswerten([], $this->konfiguration());
        $this->assertNull($a->gesamtNote);
        $this->assertNull($a->wert(Zielgroesse::parse('modul:100')));
    }

    #[Test]
    public function toarray_ist_serialisierbar(): void
    {
        $a = (new Rechenkern)->auswerten([$this->fach(self::MATHE, 1, 4.0), $this->modul(100, 5.0)], $this->konfiguration());
        $json = json_encode($a->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"gesamt"', $json);
        $this->assertCount(2, $a->toArray()['elemente']);
    }

    private function konfiguration(float $gewichtBms = 1.0): Konfiguration
    {
        return $this->testKonfiguration($gewichtBms);
    }
}
