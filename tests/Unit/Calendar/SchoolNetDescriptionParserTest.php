<?php

namespace Tests\Unit\Calendar;

use App\Services\Calendar\SchoolNetDescriptionParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchoolNetDescriptionParserTest extends TestCase
{
    private const string BEISPIEL = "Prüfung\n902-INXX 99 B-musmax LB1: Beta-Grundlagen und Begriffe\nPrüfungsstoff\nPrüfungsart: Onlineprüfung in Microsoft Teams\nDauer: 45 Minuten\nHilfsmittel: Keine Unterlagen erlaubt\nSie können:\n- die Grundlagen und Vorteile von Beta-Systemen erklären.\n- zentrale Begriffe und Bestandteile des Beta-Verfahrens beschreiben.\nGewichtung\n0.33333333\nPrüfungsdatum festgelegt am\n01.09.2026 13:54";

    #[Test]
    public function zerlegt_schulnetz_pruefung_in_felder(): void
    {
        $r = (new SchoolNetDescriptionParser)->parse('902-INXX 99 B-musmax', self::BEISPIEL);

        $this->assertTrue($r['is_exam']);
        $this->assertSame('902', $r['module_number']);
        $this->assertSame('INXX 99 B', $r['class_name']);
        $this->assertSame('musmax', $r['teacher']);
        $this->assertSame('LB1', $r['label']);
        $this->assertSame('Beta-Grundlagen und Begriffe', $r['title']);
        $this->assertSame('Onlineprüfung in Microsoft Teams', $r['exam_type']);
        $this->assertSame(45, $r['duration_minutes']);
        $this->assertSame('Keine Unterlagen erlaubt', $r['aids']);
        $this->assertSame(33.33, $r['weight_percent']);
        $this->assertSame('2026-09-01 13:54', $r['scheduled_at']->format('Y-m-d H:i'));
        $this->assertStringStartsWith('Sie können:', $r['material']);
        $this->assertStringContainsString('- zentrale Begriffe', $r['material']);
        $this->assertStringNotContainsString('Gewichtung', $r['material']);
    }

    #[Test]
    public function unbekannte_abschnitte_landen_im_stoff_und_nichts_wirft(): void
    {
        $text = "Prüfung\n902-INXX 99 B-musmax LB2: DHCP\nBemerkung\nBitte Laptop mitnehmen\nGewichtung\nunbekannt";
        $r = (new SchoolNetDescriptionParser)->parse(null, $text);

        $this->assertSame('DHCP', $r['title']);
        $this->assertStringContainsString('Bemerkung', $r['material']);
        $this->assertStringContainsString('Laptop', $r['material']);
        $this->assertNull($r['weight_percent']);
    }

    #[Test]
    public function kopfzeilen_mit_mehreren_klassen_und_zweiter_lehrperson(): void
    {
        $p = new SchoolNetDescriptionParser;

        $abu = $p->header('ABU-INXX 99 A,INXX 99 B-musmia');
        $this->assertSame('ABU', $abu['course_code']);
        $this->assertNull($abu['module_number']);
        $this->assertSame('INXX 99 A, INXX 99 B', $abu['class_name']);
        $this->assertSame('musmia', $abu['teacher']);

        $spo = $p->header('SPO-INXX 99 A,INXX 99 B-musmoe (muslea)');
        $this->assertSame('musmoe (muslea)', $spo['teacher']);

        $this->assertNull($p->header('Elternabend')['course_code']);
    }

    #[Test]
    public function gewichtung_und_dauer_tolerant(): void
    {
        $p = new SchoolNetDescriptionParser;

        $this->assertSame(50.0, $p->weight('0.5'));
        $this->assertSame(33.33, $p->weight('1/3'));
        $this->assertSame(25.0, $p->weight('25 %'));
        $this->assertSame(100.0, $p->weight('1'));
        $this->assertNull($p->weight('0'));

        $this->assertSame(90, $p->duration('90 min'));
        $this->assertSame(90, $p->duration('1.5 Stunden'));
        $this->assertSame(90, $p->duration('2 Lektionen'));
        $this->assertSame(75, $p->duration('1 h 15 min'));
        $this->assertNull($p->duration('offen'));
    }

    #[Test]
    public function html_und_ical_escapes_werden_bereinigt(): void
    {
        $r = (new SchoolNetDescriptionParser)->parse('x', 'Prüfung<br>905-INXX 99 B-abcdef LB1: Netzwerk\\, Grundlagen<br>Dauer: 30 Minuten');

        $this->assertSame('905', $r['module_number']);
        $this->assertSame('Netzwerk, Grundlagen', $r['title']);
        $this->assertSame(30, $r['duration_minutes']);
    }

    #[Test]
    public function kopfzeile_ohne_kurscode_faellt_auf_summary_zurueck_und_absaetze_bleiben(): void
    {
        $text = "Prüfung\nLB3 Netzwerktechnik\nPrüfungsstoff\nTeil A\n\nTeil B\nGewichtung: 25 %\nDauer: 90\nPrüfungsdatum festgelegt am\nnoch offen";
        $r = (new SchoolNetDescriptionParser)->parse('905-INXX 99 B-abcdef', $text);

        $this->assertSame('905', $r['module_number']);
        $this->assertSame('abcdef', $r['teacher']);
        $this->assertSame("Teil A\n\nTeil B", $r['material']);
        $this->assertSame(25.0, $r['weight_percent']);
        $this->assertSame(90, $r['duration_minutes']);
        $this->assertNull($r['scheduled_at']);
    }

    #[Test]
    public function ungueltiges_datum_laeuft_ueber_statt_zu_werfen(): void
    {
        // Carbon wirft bei 31.02. nicht, sondern rechnet weiter (03.03.) – dokumentiert das tolerante Verhalten.
        $r = (new SchoolNetDescriptionParser)->parse('x', "Prüfungsdatum festgelegt am\n31.02.2026 08:00");

        $this->assertSame('2026-03-03 08:00', $r['scheduled_at']->format('Y-m-d H:i'));
    }
}
