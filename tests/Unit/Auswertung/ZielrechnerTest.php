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
    public function gesuchte_note_wird_nur_in_einfliessende_unbekannte_gesetzt(): void
    {
        $leistungen = [$this->fach(self::MATHE, 1, null), $this->fach(self::DEUTSCH, 1, null), $this->fach(self::DEUTSCH, 1, 3.0)];
        $rechner = new Zielrechner;
        $k = $this->testKonfiguration();

        $fach = $rechner->mitGesuchterNote($leistungen, Zielgroesse::parse('fach:10@semester:1'), 4.5, $k);
        $this->assertSame([4.5, null, 3.0], array_map(fn (Leistung $l) => $l->wert, $fach));

        $gesamt = $rechner->mitGesuchterNote($leistungen, Zielgroesse::parse('gesamt'), 4.5, $k);
        $this->assertSame([4.5, 4.5, 3.0], array_map(fn (Leistung $l) => $l->wert, $gesamt));
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
    public function bestehen_beachtet_grenzen_fuer_ungenuegende_teile_und_minuspunkte(): void
    {
        $baum = fn (array $regel) => Baum::ausArray(1, 'QV', ['id' => 1, 'code' => 'qv', 'typ' => 'gruppe', 'rundung' => 0.1, ...$regel, 'kinder' => [
            ['id' => 2, 'code' => 'a', 'typ' => 'manuell'],
            ['id' => 3, 'code' => 'b', 'typ' => 'manuell'],
        ]]);

        // Für die Gesamtnote reichten (5.0 + 2.9) / 2; kein ungenügender Teil erlaubt → 4.0
        $k = $this->testKonfiguration()->mitBaeumen([$baum(['max_ungenuegend' => 0])]);
        $r = (new Zielrechner)->loeseBestehen([Leistung::position(2, 5.0), Leistung::position(3, null)], $k);
        $this->assertSame(Zielrechner::BENOETIGT, $r['status']);
        $this->assertSame(4.0, $r['note']);

        // Für die Gesamtnote reichten (6.0 + 1.9) / 2; höchstens 0.5 Minuspunkte → 3.5
        $k = $this->testKonfiguration()->mitBaeumen([$baum(['max_minuspunkte' => 0.5])]);
        $r = (new Zielrechner)->loeseBestehen([Leistung::position(2, 6.0), Leistung::position(3, null)], $k);
        $this->assertSame(3.5, $r['note']);
    }

    #[Test]
    public function bestehen_unerreichbar_nennt_die_verletzte_regel_aus_dem_heutigen_stand(): void
    {
        // Informatikkompetenzen 3.0 unter der Fallnote 4.0: keine IPA-Note hilft, der Grund ist aber nicht endgültig
        $baum = Baum::ausArray(1, 'QV', ['id' => 1, 'code' => 'qv', 'typ' => 'gruppe', 'rundung' => 0.1, 'kinder' => [
            ['id' => 2, 'code' => 'ipa', 'typ' => 'manuell'],
            ['id' => 3, 'code' => 'ik', 'typ' => 'kategorie', 'kategorie_id' => self::FACH, 'fallnote' => 4.0],
        ]]);
        $r = (new Zielrechner)->loeseBestehen([$this->modul(100, 3.0), Leistung::position(2, null)], $this->testKonfiguration()->mitBaeumen([$baum]));

        $this->assertSame(Zielrechner::UNERREICHBAR, $r['status']);
        $this->assertSame(['ik'], array_map(fn ($g) => $g->code, $r['gruende']));
        $this->assertFalse($r['gruende'][0]->definitiv);

        // Ohne Fallnote der Wurzel gilt wie im Baumstatus nur die Regel des Baums: die Gesamtnote 3.5 verhindert das Bestehen nicht
        $baum = Baum::ausArray(1, 'QV', ['id' => 1, 'code' => 'qv', 'typ' => 'gruppe', 'rundung' => 0.1, 'kinder' => [
            ['id' => 2, 'code' => 'ipa', 'typ' => 'manuell'],
            ['id' => 3, 'code' => 'ik', 'typ' => 'kategorie', 'kategorie_id' => self::FACH],
        ]]);
        $r = (new Zielrechner)->loeseBestehen([$this->modul(100, 1.0), Leistung::position(2, null)], $this->testKonfiguration()->mitBaeumen([$baum]));

        $this->assertSame(Zielrechner::ERREICHT, $r['status']);
        $this->assertSame([], $r['gruende']);
    }

    #[Test]
    public function entscheidende_positionen_folgen_gewicht_und_regeln_bis_zur_wurzel(): void
    {
        $baum = fn (?float $fallnote) => Baum::ausArray(1, 'QV', ['code' => 'qv', 'typ' => 'gruppe', 'fallnote' => $fallnote, 'kinder' => [
            ['code' => 'traegt', 'typ' => 'manuell'],
            ['code' => 'g0', 'typ' => 'gruppe', 'gewicht' => 0, 'kinder' => [
                ['code' => 'g0_ohne_regel', 'typ' => 'manuell'],
                ['code' => 'g0_mit_fallnote', 'typ' => 'manuell', 'fallnote' => 4.0],
            ]],
            ['code' => 'gz', 'typ' => 'gruppe', 'zaehlt' => false, 'kinder' => [
                ['code' => 'gz_mit_fallnote', 'typ' => 'manuell', 'fallnote' => 4.0],
            ]],
            ['code' => 'gu', 'typ' => 'gruppe', 'gewicht' => 0, 'max_ungenuegend' => 1, 'kinder' => [
                ['code' => 'gu_ohne_gewicht', 'typ' => 'manuell', 'gewicht' => 0],
            ]],
            ['code' => 'ohne_gewicht', 'typ' => 'manuell', 'gewicht' => 0],
            ['code' => 'ohne_gewicht_mit_fallnote', 'typ' => 'manuell', 'gewicht' => 0, 'fallnote' => 4.5],
        ]]);

        $this->assertSame(
            ['traegt', 'g0_mit_fallnote', 'gu_ohne_gewicht', 'ohne_gewicht_mit_fallnote'],
            array_map(fn ($k) => $k->code, Zielrechner::entscheidendePositionen($baum(4.0)->wurzel)),
        );
        // Ohne Fallnote der Gesamtnote entscheidet, was nur die Gesamtnote trägt, nicht übers Bestehen (wie BaumRechner)
        $this->assertSame(
            ['g0_mit_fallnote', 'gu_ohne_gewicht', 'ohne_gewicht_mit_fallnote'],
            array_map(fn ($k) => $k->code, Zielrechner::entscheidendePositionen($baum(null)->wurzel)),
        );
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
