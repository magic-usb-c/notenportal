<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\Notenbaum\Baum;
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
    public function zaehlt_nur_offene_pruefungen_mit_einfluss_auf_das_ziel(): void
    {
        // Die offene Deutschprüfung und das offene Modul ändern an Mathematik nichts: «in jeder der 2», nicht der 4.
        $leistungen = [
            $this->fach(self::MATHE, 1, 3.0), $this->fach(self::MATHE, 1, null, 50), $this->fach(self::MATHE, 1, null, 50),
            $this->fach(self::DEUTSCH, 1, null), $this->modul(100, null),
        ];
        $r = (new Zielrechner)->loese($leistungen, Zielgroesse::parse('fach:10@semester:1'), 4.0, $this->testKonfiguration());

        $this->assertSame(Zielrechner::BENOETIGT, $r['status']);
        $this->assertSame(4.5, $r['note']);
        $this->assertSame(2, $r['unbekannte']);

        // Auf den Gesamtschnitt wirken alle vier.
        $gesamt = (new Zielrechner)->loese($leistungen, Zielgroesse::parse('gesamt'), 4.0, $this->testKonfiguration());
        $this->assertSame(4, $gesamt['unbekannte']);
    }

    #[Test]
    public function rundung_auf_zwischenebenen_verdeckt_keine_offene_pruefung(): void
    {
        // Einzeln verschluckt die Semesterrundung von Englisch jede der drei kleinen Prüfungen, gemeinsam
        // auf 1.0 kippen sie das Ziel: alle gehören in den Satz. Die Prüfung ohne Gewicht bewegt nichts.
        $leistungen = [
            $this->fach(self::ENGLISCH, 1, 5.5), $this->fach(self::ENGLISCH, 1, null, 20), $this->fach(self::ENGLISCH, 1, null, 10),
            $this->fach(self::ENGLISCH, 1, null, 5), $this->fach(self::ENGLISCH, 1, null, 0),
            $this->fach(self::MATHE, 1, 6.0), $this->fach(self::MATHE, 1, null), $this->fach(self::ENGLISCH, 2, 3.5),
        ];
        $r = (new Zielrechner)->loese($leistungen, Zielgroesse::parse('gesamt'), 4.5, $this->testKonfiguration());

        $this->assertSame(Zielrechner::BENOETIGT, $r['status']);
        $this->assertSame(4, $r['unbekannte']);
    }

    #[Test]
    public function zaehlt_im_notenbaum_nur_was_die_wurzel_traegt(): void
    {
        // Wurzel = IPA (Position) und Fachunterricht; ÜK hängt mit Gewicht 0 daneben, BMS gar nicht im Baum.
        $baum = Baum::ausArray(1, 'QV', ['id' => 1, 'code' => 'qv', 'typ' => 'gruppe', 'rundung' => 0.1, 'kinder' => [
            ['id' => 2, 'code' => 'ipa', 'typ' => 'manuell', 'gewicht' => 1],
            ['id' => 3, 'code' => 'fach', 'typ' => 'kategorie', 'kategorie_id' => self::FACH, 'gewicht' => 1],
            ['id' => 4, 'code' => 'uek', 'typ' => 'kategorie', 'kategorie_id' => self::UEK, 'gewicht' => 0],
        ]]);
        $k = $this->testKonfiguration()->mitBaeumen([$baum]);
        $leistungen = [
            $this->modul(100, 4.0, 50), $this->modul(100, null, 50), Leistung::position(2, null),
            $this->modul(101, null, kategorie: self::UEK), $this->fach(self::MATHE, 1, null),
        ];

        $r = (new Zielrechner)->loese($leistungen, Zielgroesse::parse('gesamt'), 4.5, $k);
        $this->assertSame(Zielrechner::BENOETIGT, $r['status']);
        $this->assertSame(2, $r['unbekannte']);

        // Ist die IPA schon erfasst, bewegt eine zweite, offene Position nichts mehr.
        $erfasst = [...$leistungen, Leistung::position(2, 5.0)];
        $this->assertSame(1, (new Zielrechner)->loese($erfasst, Zielgroesse::parse('gesamt'), 4.5, $k)['unbekannte']);
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
