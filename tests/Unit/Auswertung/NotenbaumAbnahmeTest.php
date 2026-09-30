<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\Notenbaum\Baum;
use App\Services\Auswertung\Notenbaum\BaumErgebnis;
use App\Services\Auswertung\Rechenkern;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Abnahmefälle aus docs/auftrag/NOTENBAUM.md §3 – die Spezifikation des Notenbaums.
 * Rein rechnerisch (ohne DB); der Weg über die Datenbank steht in Tests\Feature\Auswertung\NotenbaumDbTest.
 */
class NotenbaumAbnahmeTest extends TestCase
{
    private const int FACH = 1;          // Fachunterricht: Schulmodule und Fächer der Berufsfachschule

    private const int UEK = 2;

    private const int BMS = 3;

    private const int ABU = 4;

    private const int ENGLISCH = 12;

    private const int MATHE = 10;

    private const int ABU_FACH = 20;

    private const int DEUTSCH = 30;

    private const int ITALIENISCH = 31;

    private const int ENGLISCH_BM = 32;

    private const int MATHE_BM = 33;

    private const int GESCHICHTE = 34;

    private const int WIRTSCHAFT = 35;

    private const int IDAF = 36;

    private const int SPORT = 40;

    // ---------------------------------------------------------------------------------------------
    // 3.1 Informatiker/in EFZ – QV-Gesamtnote
    // ---------------------------------------------------------------------------------------------

    #[Test]
    public function fall_a_regulaer(): void
    {
        $e = $this->efz(ipa: 5.0, abuErfahrung: 4.5, schlussarbeit: 5.0, schlusspruefung: 4.0, egk: 4.5, schulmodule: 5.0, uek: 4.0);

        $this->assertEqualsWithDelta(4.5, $e->knoten('ab')->note, 1e-9);
        $this->assertEqualsWithDelta(4.8, $e->knoten('ik')->note, 1e-9);
        $this->assertEqualsWithDelta(4.79, $e->wurzel()->schnitt, 1e-9);
        $this->assertEqualsWithDelta(4.8, $e->wurzel()->note, 1e-9);
        $this->assertSame(BaumErgebnis::BESTANDEN, $e->status);
        $this->assertSame([], $e->gruende);
    }

    #[Test]
    public function fall_b_fallnote_ipa_mit_begruendung(): void
    {
        $e = $this->efz(ipa: 3.5, abuErfahrung: 4.5, schlussarbeit: 5.0, schlusspruefung: 4.0, egk: 4.5, schulmodule: 5.0, uek: 4.0);

        $this->assertEqualsWithDelta(4.2, $e->wurzel()->note, 1e-9);
        $this->assertSame(BaumErgebnis::NICHT_BESTANDEN, $e->status);
        $this->assertCount(1, $e->gruende);
        $this->assertSame('ipa', $e->gruende[0]->code);
        $this->assertSame('fallnote', $e->gruende[0]->regel);
        $this->assertEqualsWithDelta(3.5, $e->gruende[0]->wert, 1e-9);
        $this->assertEqualsWithDelta(4.0, $e->gruende[0]->grenze, 1e-9);
        $this->assertStringContainsString('Praktische Arbeit', $e->gruende[0]->text());
    }

    #[Test]
    public function fall_c_fallnote_informatikkompetenzen(): void
    {
        $e = $this->efz(ipa: 5.0, abuErfahrung: 4.5, schlussarbeit: 5.0, schlusspruefung: 4.0, egk: 4.5, schulmodule: 3.5, uek: 4.5);

        $this->assertEqualsWithDelta(3.7, $e->knoten('ik')->note, 1e-9);
        $this->assertGreaterThanOrEqual(4.0, $e->wurzel()->note);
        $this->assertSame(BaumErgebnis::NICHT_BESTANDEN, $e->status);
        $this->assertSame(['ik'], array_map(fn ($g) => $g->code, $e->gruende));
    }

    #[Test]
    public function fall_d_die_gewichtung_wirkt_wirklich(): void
    {
        $e = $this->efz(ipa: 5.0, abuErfahrung: 4.5, schlussarbeit: 5.0, schlusspruefung: 4.0, egk: 4.5, schulmodule: 6.0, uek: 1.0);

        // 6,0 × 0,8 + 1,0 × 0,2 = 5,0 – ein ungewichtetes Mittel ergäbe 3,5
        $this->assertEqualsWithDelta(5.0, $e->knoten('ik')->note, 1e-9);
        $this->assertEqualsWithDelta(5.0, $e->knoten('ik_bfs')->note * 0.8 + $e->knoten('ik_uek')->note * 0.2, 1e-9);
    }

    #[Test]
    public function fall_d_ohne_baum_rechnet_der_bestand_wie_bisher(): void
    {
        // Gleiche Noten, aber kein Baum: die bisherige flache Kategorie-Gewichtung bleibt unverändert (§3.4).
        $leistungen = [$this->modul(100, self::FACH, 6.0), $this->modul(200, self::UEK, 1.0)];

        $ohne = (new Rechenkern)->auswerten($leistungen, $this->konfiguration());
        $mit = (new Rechenkern)->auswerten($leistungen, $this->konfiguration()->mitBaeumen([$this->efzBaum()]));

        $this->assertEqualsWithDelta(3.5, $ohne->gesamtNote, 1e-9);
        $this->assertSame([], $ohne->baeume);
        $this->assertEqualsWithDelta(5.0, $mit->baeume[1]->knoten('ik')->note, 1e-9);
    }

    #[Test]
    public function mit_baum_ist_die_gesamtnote_die_wurzel_des_baums(): void
    {
        $a = $this->efzAuswertung(ipa: 5.0, abuErfahrung: 4.5, schlussarbeit: 5.0, schlusspruefung: 4.0, egk: 4.5, schulmodule: 5.0, uek: 4.0);

        $this->assertEqualsWithDelta(4.8, $a->gesamtNote, 1e-9);
        $this->assertEqualsWithDelta(4.79, $a->gesamtSchnitt, 1e-9);
    }

    #[Test]
    public function erweiterte_grundkompetenzen_mitteln_alle_semesterzeugnisnoten_gemeinsam(): void
    {
        // Englisch in drei Semestern, Mathematik in einem: Mittel der vier Zeugnisnoten, nicht Mittel der Fachschnitte.
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::ENGLISCH, 1, 4.0, self::FACH), $this->fach(self::ENGLISCH, 2, 4.0, self::FACH),
            $this->fach(self::ENGLISCH, 3, 4.0, self::FACH), $this->fach(self::MATHE, 1, 6.0, self::FACH),
        ], $this->konfiguration()->mitBaeumen([$this->efzBaum()]));

        // (4+4+4+6)/4 = 4,5; Mittel der Fachschnitte wäre (4+6)/2 = 5,0
        $this->assertEqualsWithDelta(4.5, $a->baeume[1]->knoten('egk')->note, 1e-9);
    }

    #[Test]
    public function fehlende_positionen_ergeben_eine_vorlaeufige_note_und_status_offen(): void
    {
        $e = $this->efz(ipa: null, abuErfahrung: 4.5, schlussarbeit: null, schlusspruefung: null, egk: 4.5, schulmodule: 5.0, uek: 4.0);

        $this->assertSame(BaumErgebnis::OFFEN, $e->status);
        $this->assertFalse($e->wurzel()->vollstaendig);
        $this->assertNull($e->knoten('ipa')->note);
        // (4,5·20 + 4,5·10 + 4,8·30) / 60 = 4,65 → 4,7
        $this->assertEqualsWithDelta(4.7, $e->wurzel()->note, 1e-9);
    }

    #[Test]
    public function schulmodule_zaehlen_nur_module_nicht_die_faecher_der_kategorie(): void
    {
        // Englisch liegt in derselben Kategorie wie die Schulmodule, gehört aber zu den erweiterten Grundkompetenzen.
        $a = (new Rechenkern)->auswerten([
            $this->modul(100, self::FACH, 5.0), $this->fach(self::ENGLISCH, 1, 3.0, self::FACH),
        ], $this->konfiguration()->mitBaeumen([$this->efzBaum()]));

        $this->assertEqualsWithDelta(5.0, $a->baeume[1]->knoten('ik_bfs')->note, 1e-9);
    }

    // ---------------------------------------------------------------------------------------------
    // 3.2 Berufsmaturität TALS1
    // ---------------------------------------------------------------------------------------------

    #[Test]
    public function bm_abschlussnote_mit_pruefung_ist_halb_pruefung_halb_erfahrung(): void
    {
        // Erfahrungsnote 5,2 = Mittel der Semesterzeugnisnoten 5,0 / 5,5 / 5,0 / 5,5 / 5,0
        $leistungen = [];
        foreach ([5.0, 5.5, 5.0, 5.5, 5.0] as $i => $wert) {
            $leistungen[] = $this->fach(self::DEUTSCH, $i + 1, $wert, self::BMS);
        }
        $leistungen[] = $this->position('deutsch_pruefung', 4.5);

        $e = $this->bm($leistungen);

        $this->assertEqualsWithDelta(5.2, $e->knoten('deutsch_erfahrung')->note, 1e-9);
        $this->assertEqualsWithDelta(4.85, $e->knoten('deutsch')->schnitt, 1e-9);
        $this->assertEqualsWithDelta(5.0, $e->knoten('deutsch')->note, 1e-9);
    }

    #[Test]
    public function bm_erfahrungsnote_auf_eine_dezimalstelle(): void
    {
        $e = $this->bm([
            $this->fach(self::GESCHICHTE, 1, 4.5, self::BMS), $this->fach(self::GESCHICHTE, 2, 5.0, self::BMS),
            $this->fach(self::GESCHICHTE, 3, 5.0, self::BMS),
        ]);

        // 14,5 / 3 = 4,8333 → Erfahrungsnote 4,8; Fach ohne Prüfung: Abschlussnote = Erfahrungsnote auf halbe Note → 5,0
        $this->assertEqualsWithDelta(4.8, $e->knoten('geschichte_erfahrung')->note, 1e-9);
        $this->assertEqualsWithDelta(5.0, $e->knoten('geschichte')->note, 1e-9);
    }

    #[Test]
    public function bm_idpa_ist_halb_idpa_halb_erfahrungsnote_idaf(): void
    {
        $e = $this->bm([
            $this->fach(self::IDAF, 1, 4.0, self::BMS), $this->fach(self::IDAF, 2, 5.0, self::BMS),
            $this->position('idpa_arbeit', 5.5),
        ]);

        // Erfahrungsnote IDAF 4,5; (4,5 + 5,5) / 2 = 5,0
        $this->assertEqualsWithDelta(4.5, $e->knoten('idaf_erfahrung')->note, 1e-9);
        $this->assertEqualsWithDelta(5.0, $e->knoten('idpa')->note, 1e-9);
    }

    #[Test]
    public function bm_promotion_bestanden_mit_zwei_ungenuegenden(): void
    {
        $p = $this->promotion([3.5, 3.5, 5.0, 5.0, 5.0, 5.0]);

        $this->assertTrue($p['erfuellt']);
        $this->assertEqualsWithDelta(4.5, $p['schnitt'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $p['minuspunkte'], 1e-9);
        $this->assertSame(2, $p['ungenuegend']);
    }

    #[Test]
    public function bm_promotion_nicht_bestanden_mit_drei_ungenuegenden_obwohl_schnitt_und_minuspunkte_passen(): void
    {
        $p = $this->promotion([3.5, 3.5, 3.5, 5.0, 5.0, 5.0]);

        $this->assertFalse($p['erfuellt']);
        $this->assertGreaterThanOrEqual(4.0, $p['schnitt']); // 4,25
        $this->assertEqualsWithDelta(1.5, $p['minuspunkte'], 1e-9);
        $this->assertSame(3, $p['ungenuegend']);
        $this->assertSame(['ungenuegend'], $p['verletzt']);
    }

    #[Test]
    public function idaf_zaehlt_nicht_zur_promotion(): void
    {
        // Eine ungenügende IDAF-Note darf die Promotion weder in der Anzahl noch in den Minuspunkten belasten.
        $p = $this->promotion([3.5, 3.5, 5.0, 5.0, 5.0, 5.0], idaf: 2.0);

        $this->assertTrue($p['erfuellt']);
        $this->assertSame(2, $p['ungenuegend']);
        $this->assertEqualsWithDelta(1.0, $p['minuspunkte'], 1e-9);
        $this->assertEqualsWithDelta(4.5, $p['schnitt'], 1e-9);
    }

    #[Test]
    public function idaf_ist_trotzdem_als_element_erfasst_und_sichtbar(): void
    {
        $a = (new Rechenkern)->auswerten([$this->fach(self::IDAF, 1, 2.0, self::BMS)], $this->konfiguration());

        $this->assertEqualsWithDelta(2.0, $a->elemente['f36s1']->note, 1e-9);
        $this->assertFalse($a->elemente['f36s1']->zaehlt);
        $this->assertNull($a->semester(1)['note']);
    }

    #[Test]
    public function bm_abschluss_bestehen_wie_promotion(): void
    {
        // Abschlussnoten 3,5 / 3,5 / 3,5 / 5,0 / 5,0 / 5,0 / 5,0 → Schnitt 4,4, Minuspunkte 1,5, aber drei ungenügende
        $leistungen = [];
        $faecher = ['deutsch' => [self::DEUTSCH, 3.5], 'italienisch' => [self::ITALIENISCH, 3.5], 'englisch' => [self::ENGLISCH_BM, 3.5], 'mathematik' => [self::MATHE_BM, 5.0]];
        foreach ($faecher as $code => [$fach, $wert]) {
            $leistungen[] = $this->fach($fach, 1, $wert, self::BMS);
            $leistungen[] = $this->position($code.'_pruefung', $wert);
        }
        $leistungen[] = $this->fach(self::GESCHICHTE, 1, 5.0, self::BMS);
        $leistungen[] = $this->fach(self::WIRTSCHAFT, 1, 5.0, self::BMS);
        $leistungen[] = $this->fach(self::IDAF, 1, 5.0, self::BMS);
        $leistungen[] = $this->position('idpa_arbeit', 5.0);

        $e = $this->bm($leistungen);

        $this->assertTrue($e->wurzel()->vollstaendig);
        $this->assertEqualsWithDelta(4.4, $e->wurzel()->note, 1e-9);
        $this->assertSame(BaumErgebnis::NICHT_BESTANDEN, $e->status);
        $this->assertSame(['ungenuegend'], array_map(fn ($g) => $g->regel, $e->gruende));

        // Mit nur zwei ungenügenden besteht dieselbe Konstellation.
        $leistungen = array_map(fn (Leistung $l) => $l->fachId === self::ENGLISCH_BM || $l->knotenId === $this->bmBaum()->knotenId('englisch_pruefung') ? $l->mitWert(5.0) : $l, $leistungen);
        $this->assertSame(BaumErgebnis::BESTANDEN, $this->bm($leistungen)->status);
    }

    // ---------------------------------------------------------------------------------------------
    // 3.3 Sport
    // ---------------------------------------------------------------------------------------------

    #[Test]
    public function sport_zaehlt_in_keiner_rechnung(): void
    {
        // Stufen (A/B/C/d) erzeugen keine rechnende Leistung; eine versehentlich numerische Sportnote
        // eines nicht zählenden Fachs darf ebenfalls nirgends eingehen.
        $a = (new Rechenkern)->auswerten([
            $this->fach(self::DEUTSCH, 1, 5.0, self::BMS), $this->fach(self::ENGLISCH_BM, 1, 5.0, self::BMS),
            $this->fach(self::SPORT, 1, 1.0, self::BMS),
        ], $this->konfiguration());

        $this->assertEqualsWithDelta(5.0, $a->semester(1)['note'], 1e-9);
        $this->assertEqualsWithDelta(5.0, $a->kategorien[self::BMS]['note'], 1e-9);
        $this->assertEqualsWithDelta(5.0, $a->gesamtNote, 1e-9);
        $this->assertTrue($a->promotion(self::BMS, 1)['erfuellt']);
    }

    #[Test]
    public function allgemeinbildung_entfaellt_mit_bm_und_die_uebrigen_gewichte_werden_hochgerechnet(): void
    {
        $baum = $this->efzBaum(['entfaellt_mit_track' => 'BMS']);
        $l = [$this->position('ipa', 5.0), $this->fach(self::ENGLISCH, 1, 4.5, self::FACH), $this->modul(100, self::FACH, 5.0), $this->modul(200, self::UEK, 4.0)];

        $mitBm = (new Rechenkern)->auswerten($l, $this->konfiguration()->mitBaeumen([$baum->fuerTrack('BMS')]))->baeume[1];
        // IPA 5.0·40 + EGK 4.5·10 + IK 4.8·30 = 389 / 80 = 4.8625 → 4.9, ohne Allgemeinbildung vollständig
        $this->assertEqualsWithDelta(4.9, $mitBm->wurzel()->note, 1e-9);
        $this->assertTrue($mitBm->wurzel()->vollstaendig);
        $this->assertSame(BaumErgebnis::BESTANDEN, $mitBm->status);
        $this->assertTrue($mitBm->knoten('ab')->knoten->entfaellt);

        // Mit ABU-Track (oder ohne Track) fehlt die Allgemeinbildung und das Ergebnis bleibt offen
        foreach (['ABU', null] as $track) {
            $ohneBm = (new Rechenkern)->auswerten($l, $this->konfiguration()->mitBaeumen([$baum->fuerTrack($track)]))->baeume[1];
            $this->assertSame(BaumErgebnis::OFFEN, $ohneBm->status);
            $this->assertFalse($ohneBm->knoten('ab')->knoten->entfaellt);
        }
    }

    #[Test]
    public function ein_nicht_zaehlender_teil_entscheidet_nicht_ueber_das_bestehen(): void
    {
        // Fallnote auf einem Teil, der nicht zählt: 3.0 darunter darf das Ergebnis nicht kippen
        $baum = $this->efzBaum(['zaehlt' => false, 'fallnote' => 4.0]);
        $l = [$this->position('ipa', 5.0), $this->position('ab_schlussarbeit', 3.0), $this->position('ab_schlusspruefung', 3.0),
            $this->fach(self::ABU_FACH, 1, 3.0, self::ABU), $this->fach(self::ENGLISCH, 1, 4.5, self::FACH),
            $this->modul(100, self::FACH, 5.0), $this->modul(200, self::UEK, 4.0)];

        $e = (new Rechenkern)->auswerten($l, $this->konfiguration()->mitBaeumen([$baum]))->baeume[1];

        $this->assertEqualsWithDelta(3.0, $e->knoten('ab')->note, 1e-9);
        $this->assertSame([], $e->gruende);
        $this->assertSame(BaumErgebnis::BESTANDEN, $e->status);
    }

    // ---------------------------------------------------------------------------------------------
    // Hilfen
    // ---------------------------------------------------------------------------------------------

    private function efz(?float $ipa, ?float $abuErfahrung, ?float $schlussarbeit, ?float $schlusspruefung, ?float $egk, ?float $schulmodule, ?float $uek): BaumErgebnis
    {
        return $this->efzAuswertung($ipa, $abuErfahrung, $schlussarbeit, $schlusspruefung, $egk, $schulmodule, $uek)->baeume[1];
    }

    private function efzAuswertung(?float $ipa, ?float $abuErfahrung, ?float $schlussarbeit, ?float $schlusspruefung, ?float $egk, ?float $schulmodule, ?float $uek): Auswertung
    {
        $l = [];
        $ipa !== null && $l[] = $this->position('ipa', $ipa);
        $schlussarbeit !== null && $l[] = $this->position('ab_schlussarbeit', $schlussarbeit);
        $schlusspruefung !== null && $l[] = $this->position('ab_schlusspruefung', $schlusspruefung);
        $abuErfahrung !== null && $l[] = $this->fach(self::ABU_FACH, 1, $abuErfahrung, self::ABU);
        $egk !== null && $l[] = $this->fach(self::ENGLISCH, 1, $egk, self::FACH);
        $schulmodule !== null && $l[] = $this->modul(100, self::FACH, $schulmodule);
        $uek !== null && $l[] = $this->modul(200, self::UEK, $uek);

        return (new Rechenkern)->auswerten($l, $this->konfiguration()->mitBaeumen([$this->efzBaum()]));
    }

    /** Baum laut LAGE.md §2 mit den Rundungen, die NOTENBAUM.md §3.1 voraussetzt. */
    /** @param  array<string, mixed>  $ab  zusätzliche Angaben am Knoten «Allgemeinbildung» */
    private function efzBaum(array $ab = []): Baum
    {
        return Baum::ausArray(1, 'Informatiker/in EFZ', [
            'code' => 'qv', 'name' => 'QV-Gesamtnote', 'typ' => 'gruppe', 'rundung' => 0.1, 'fallnote' => 4.0,
            'kinder' => [
                ['code' => 'ipa', 'name' => 'Praktische Arbeit', 'typ' => 'manuell', 'gewicht' => 40, 'rundung' => 0.1, 'fallnote' => 4.0],
                $ab + ['code' => 'ab', 'name' => 'Allgemeinbildung', 'typ' => 'gruppe', 'gewicht' => 20, 'rundung' => 0.1, 'kinder' => [
                    ['code' => 'ab_erfahrung', 'name' => 'Erfahrungsnote', 'typ' => 'kategorie', 'kategorie_id' => self::ABU, 'rundung' => 0.5],
                    ['code' => 'ab_schlussarbeit', 'name' => 'Schlussarbeit', 'typ' => 'manuell'],
                    ['code' => 'ab_schlusspruefung', 'name' => 'Schlussprüfung', 'typ' => 'manuell'],
                ]],
                ['code' => 'egk', 'name' => 'Erweiterte Grundkompetenzen', 'typ' => 'faecher', 'faecher' => [self::ENGLISCH, self::MATHE], 'gewicht' => 10, 'rundung' => 0.5],
                ['code' => 'ik', 'name' => 'Informatikkompetenzen', 'typ' => 'gruppe', 'gewicht' => 30, 'rundung' => 0.1, 'fallnote' => 4.0, 'kinder' => [
                    ['code' => 'ik_bfs', 'name' => 'Modulnoten Berufsfachschule', 'typ' => 'kategorie', 'kategorie_id' => self::FACH, 'elementtyp' => 'modul', 'gewicht' => 80],
                    ['code' => 'ik_uek', 'name' => 'Modulnoten überbetriebliche Kurse', 'typ' => 'kategorie', 'kategorie_id' => self::UEK, 'elementtyp' => 'modul', 'gewicht' => 20],
                ]],
            ],
        ]);
    }

    /** @param  list<Leistung>  $leistungen */
    private function bm(array $leistungen): BaumErgebnis
    {
        return (new Rechenkern)->auswerten($leistungen, $this->konfiguration()->mitBaeumen([$this->bmBaum()]))->baeume[2];
    }

    private function bmBaum(): Baum
    {
        $mitPruefung = fn (string $code, string $name, int $fach) => ['code' => $code, 'name' => $name, 'typ' => 'gruppe', 'rundung' => 0.5, 'kinder' => [
            ['code' => $code.'_erfahrung', 'name' => 'Erfahrungsnote', 'typ' => 'faecher', 'faecher' => [$fach], 'rundung' => 0.1],
            ['code' => $code.'_pruefung', 'name' => 'Abschlussprüfung', 'typ' => 'manuell', 'rundung' => 0.5],
        ]];
        $ohnePruefung = fn (string $code, string $name, int $fach) => ['code' => $code, 'name' => $name, 'typ' => 'gruppe', 'rundung' => 0.5, 'kinder' => [
            ['code' => $code.'_erfahrung', 'name' => 'Erfahrungsnote', 'typ' => 'faecher', 'faecher' => [$fach], 'rundung' => 0.1],
        ]];

        return Baum::ausArray(2, 'BM TALS1', [
            'code' => 'bm', 'name' => 'Berufsmaturität', 'typ' => 'gruppe', 'rundung' => 0.1,
            'fallnote' => 4.0, 'max_ungenuegend' => 2, 'max_minuspunkte' => 2.0,
            'kinder' => [
                $mitPruefung('deutsch', 'Deutsch', self::DEUTSCH),
                $mitPruefung('italienisch', 'Italienisch', self::ITALIENISCH),
                $mitPruefung('englisch', 'Englisch', self::ENGLISCH_BM),
                $mitPruefung('mathematik', 'Mathematik', self::MATHE_BM),
                $ohnePruefung('geschichte', 'Geschichte und Politik', self::GESCHICHTE),
                $ohnePruefung('wirtschaft', 'Wirtschaft und Recht', self::WIRTSCHAFT),
                ['code' => 'idpa', 'name' => 'Interdisziplinäres Arbeiten', 'typ' => 'gruppe', 'rundung' => 0.5, 'kinder' => [
                    ['code' => 'idaf_erfahrung', 'name' => 'Erfahrungsnote IDAF', 'typ' => 'faecher', 'faecher' => [self::IDAF], 'rundung' => 0.1],
                    ['code' => 'idpa_arbeit', 'name' => 'IDPA', 'typ' => 'manuell', 'rundung' => 0.5],
                ]],
            ],
        ]);
    }

    /**
     * Promotion eines Semesters über sechs BM-Fächer.
     *
     * @param  list<float>  $noten
     * @return array{erfuellt: bool, schnitt: ?float, ungenuegend: int, minuspunkte: float, verletzt: list<string>}
     */
    private function promotion(array $noten, ?float $idaf = null): array
    {
        $faecher = [self::DEUTSCH, self::ITALIENISCH, self::ENGLISCH_BM, self::MATHE_BM, self::GESCHICHTE, self::WIRTSCHAFT];
        $l = [];
        foreach ($noten as $i => $wert) {
            $l[] = $this->fach($faecher[$i], 1, $wert, self::BMS);
        }
        if ($idaf !== null) {
            $l[] = $this->fach(self::IDAF, 1, $idaf, self::BMS);
        }

        return (new Rechenkern)->auswerten($l, $this->konfiguration())->promotion(self::BMS, 1);
    }

    private function konfiguration(): Konfiguration
    {
        $regel = fn (string $code, int $sort, ?float $min = null, ?int $maxUng = null, ?float $maxMinus = null) => [
            'code' => $code, 'name' => $code, 'sortierung' => $sort, 'rundung_element' => 0.5, 'rundung_schnitt' => 0.1,
            'gewicht_gesamt' => 1.0, 'promotion_min_schnitt' => $min, 'promotion_max_ungenuegend' => $maxUng,
            'promotion_max_minuspunkte' => $maxMinus,
        ];
        $semester = [];
        foreach (range(1, 8) as $i) {
            $jahr = 2024 + intdiv($i - 1, 2);
            $semester[$i] = $i % 2 === 1
                ? ['bezeichnung' => "HS{$jahr}", 'sortierung' => $i, 'start' => "{$jahr}-08-01", 'ende' => ($jahr + 1).'-01-31']
                : ['bezeichnung' => 'FS'.($jahr + 1), 'sortierung' => $i, 'start' => ($jahr + 1).'-02-01', 'ende' => ($jahr + 1).'-07-31'];
        }

        return new Konfiguration(
            kategorien: [
                self::FACH => $regel('FACH', 10),
                self::UEK => $regel('UEK', 20),
                self::BMS => $regel('BMS', 30, 4.0, 2, 2.0),
                self::ABU => $regel('ABU', 40),
            ],
            semester: $semester,
            faecher: [
                self::MATHE => 'Mathematik', self::ENGLISCH => 'Englisch', self::ABU_FACH => 'ABU',
                self::DEUTSCH => 'Deutsch', self::ITALIENISCH => 'Italienisch', self::ENGLISCH_BM => 'Englisch BM',
                self::MATHE_BM => 'Mathematik BM', self::GESCHICHTE => 'Geschichte und Politik',
                self::WIRTSCHAFT => 'Wirtschaft und Recht', self::IDAF => 'IDAF', self::SPORT => 'Sport',
            ],
            module: [100 => ['nummer' => '900', 'titel' => 'Testmodul Schule', 'ziel' => 100.0], 200 => ['nummer' => '990', 'titel' => 'Testmodul ÜK', 'ziel' => 100.0]],
            nichtZaehlend: [self::IDAF, self::SPORT],
        );
    }

    private function fach(int $fachId, int $semesterId, float $wert, int $kategorie): Leistung
    {
        return new Leistung($kategorie, $fachId, null, $semesterId, null, $wert);
    }

    private function modul(int $modulId, int $kategorie, float $wert): Leistung
    {
        return new Leistung($kategorie, null, $modulId, null, '2025-03-01', $wert);
    }

    /** Von Hand erfasste Position (IPA, Schlussarbeit, Abschlussprüfung …), über den Code des Knotens. */
    private function position(string $code, float $wert): Leistung
    {
        $id = $this->efzBaum()->knotenId($code) ?? $this->bmBaum()->knotenId($code);
        $this->assertNotNull($id, "Knoten «{$code}» fehlt im Testbaum.");

        return Leistung::position($id, $wert);
    }
}
