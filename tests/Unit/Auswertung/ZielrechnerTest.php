<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Zielgroesse;
use App\Services\Auswertung\Zielrechner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ZielrechnerTest extends TestCase
{
    use AuswertungTestHilfen;

    #[Test]
    public function berechnet_benoetigte_note_fuer_modul_mit_rundung(): void
    {
        // (4.0 + x) / 2 muss mindestens 4.75 sein, damit die Modulnote auf 5.0 rundet
        $r = (new Zielrechner)->loese(
            [$this->modul(100, 4.0, 50), $this->modul(100, null, 50)],
            Zielgroesse::parse('modul:100'), 5.0, $this->testKonfiguration()
        );

        $this->assertSame(Zielrechner::BENOETIGT, $r['status']);
        $this->assertSame(5.5, $r['note']);
        $this->assertSame(5.0, $r['resultat']);
    }

    #[Test]
    public function rechnet_durch_alle_ebenen_bis_zum_gesamtschnitt(): void
    {
        // BMS 4.0; Fachunterricht = gerundete Modulnote. (4.0 + Modulnote) / 2 >= 4.5 → Modulnote 5.0 → x >= 4.75
        $r = (new Zielrechner)->loese(
            [$this->fach(self::MATHE, 1, 4.0), $this->modul(100, null)],
            Zielgroesse::parse('gesamt'), 4.5, $this->testKonfiguration()
        );

        $this->assertSame(Zielrechner::BENOETIGT, $r['status']);
        $this->assertSame(4.75, $r['note']);
    }

    #[Test]
    public function mehrere_offene_pruefungen_teilen_sich_die_benoetigte_note(): void
    {
        // Mathe: 3.0 (100 %) + zwei offene à 50 %: (3 + x) / 2 >= 3.75 → x >= 4.5
        $r = (new Zielrechner)->loese(
            [$this->fach(self::MATHE, 1, 3.0), $this->fach(self::MATHE, 1, null, 50), $this->fach(self::MATHE, 1, null, 50)],
            Zielgroesse::parse('fach:10@semester:1'), 4.0, $this->testKonfiguration()
        );

        $this->assertSame(4.5, $r['note']);
        $this->assertSame(2, $r['unbekannte']);
    }

    #[Test]
    public function meldet_unerreichbar_mit_maximum(): void
    {
        $r = (new Zielrechner)->loese(
            [$this->modul(100, 1.0, 90), $this->modul(100, null, 10)],
            Zielgroesse::parse('modul:100'), 4.0, $this->testKonfiguration()
        );

        $this->assertSame(Zielrechner::UNERREICHBAR, $r['status']);
        $this->assertSame(1.5, $r['maximum']);
    }

    #[Test]
    public function meldet_erreicht_wenn_schon_eine_eins_reicht(): void
    {
        $r = (new Zielrechner)->loese(
            [$this->modul(100, 6.0, 90), $this->modul(100, null, 10)],
            Zielgroesse::parse('modul:100'), 4.0, $this->testKonfiguration()
        );

        $this->assertSame(Zielrechner::ERREICHT, $r['status']);
        $this->assertSame(5.5, $r['minimum']);
    }

    #[Test]
    public function erkennt_pruefungen_ohne_einfluss_auf_das_ziel(): void
    {
        $r = (new Zielrechner)->loese(
            [$this->fach(self::DEUTSCH, 1, 4.0), $this->fach(self::MATHE, 1, null)],
            Zielgroesse::parse('fach:11@semester:1'), 5.0, $this->testKonfiguration()
        );

        $this->assertSame(Zielrechner::OHNE_EINFLUSS, $r['status']);
    }

    #[Test]
    public function ohne_unbekannte_nur_aktueller_wert(): void
    {
        $r = (new Zielrechner)->loese([$this->fach(self::MATHE, 1, 4.5)], Zielgroesse::parse('kategorie:3'), 5.0, $this->testKonfiguration());

        $this->assertSame(Zielrechner::KEINE_UNBEKANNTEN, $r['status']);
        $this->assertSame(4.5, $r['aktuell']);
    }

    #[Test]
    public function kurve_ist_monoton(): void
    {
        $kurve = (new Zielrechner)->kurve(
            [$this->fach(self::MATHE, 1, 4.0), $this->modul(100, null)],
            Zielgroesse::parse('gesamt'), $this->testKonfiguration()
        );

        $this->assertCount(21, $kurve);
        $werte = array_column($kurve, 'wert');
        $sortiert = $werte;
        sort($sortiert);
        $this->assertSame($sortiert, $werte);
    }

    #[Test]
    public function zielgroesse_parst_und_serialisiert(): void
    {
        foreach (['gesamt', 'kategorie:3', 'kategorie:3@semester:2', 'semester:2', 'fach:10', 'fach:10@semester:1', 'modul:100'] as $text) {
            $this->assertSame($text, (string) Zielgroesse::parse($text));
        }

        $this->expectException(\InvalidArgumentException::class);
        Zielgroesse::parse('modul:1@semester:2');
    }
}
