<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Services\Import\WertParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WertParserTest extends TestCase
{
    #[Test]
    public function datum_liest_schweizer_punkt_und_iso_formate(): void
    {
        $this->assertSame('2026-03-02', WertParser::datum('02.03.2026'));
        $this->assertSame('2026-03-02', WertParser::datum('2.3.2026'));
        $this->assertSame('2026-03-02', WertParser::datum('2026-03-02'));
        $this->assertSame('2026-03-02', WertParser::datum('02.03.2026 08:15'));
        $this->assertNull(WertParser::datum(''));
        $this->assertNull(WertParser::datum('nicht ein datum'));
        $this->assertNull(WertParser::datum('31.02.2026')); // gibt es nicht
    }

    #[Test]
    public function datum_liest_excel_seriennummer(): void
    {
        $this->assertSame('2026-04-14', WertParser::datum('46126'));
    }

    #[Test]
    public function datum_mehrdeutig_nur_bei_schraegstrich_mit_vertauschbarem_tag_und_monat(): void
    {
        $this->assertTrue(WertParser::datumMehrdeutig('03/04/2026'));
        $this->assertFalse(WertParser::datumMehrdeutig('13/04/2026')); // Tag > 12: eindeutig d/m
        $this->assertFalse(WertParser::datumMehrdeutig('04/04/2026')); // Tag = Monat: egal wie gelesen, gleiches Datum
        $this->assertFalse(WertParser::datumMehrdeutig('02.03.2026')); // Punkt-Format gilt als eindeutig
        $this->assertFalse(WertParser::datumMehrdeutig('2026-03-02'));
    }

    #[Test]
    public function note_akzeptiert_komma_und_05_schritte_zwischen_1_und_6(): void
    {
        $this->assertSame(4.5, WertParser::note('4,5'));
        $this->assertSame(4.5, WertParser::note('4.5'));
        $this->assertSame(5.75, WertParser::note('5.75'));
        $this->assertNull(WertParser::note('4.33')); // kein 0.05-Schritt
        $this->assertNull(WertParser::note('0.5')); // ausserhalb 1–6
        $this->assertNull(WertParser::note('6.5'));
        $this->assertNull(WertParser::note('abc'));
        $this->assertNull(WertParser::note(''));
    }

    #[Test]
    public function note_prueft_den_bereich_vor_dem_runden(): void
    {
        // 0.999 darf nicht auf 1.0 aufgerundet und dadurch als gültig durchgehen.
        $this->assertNull(WertParser::note('0.999'));
        $this->assertNull(WertParser::note('6.004'));
        $this->assertSame(1.0, WertParser::note('1.0'));
        $this->assertSame(6.0, WertParser::note('6.0'));
    }

    #[Test]
    public function gewicht_deutet_werte_unter_1_als_anteil_und_prozent_direkt(): void
    {
        $this->assertNull(WertParser::gewicht(''));
        $this->assertSame(50.0, WertParser::gewicht('50%'));
        $this->assertSame(100.0, WertParser::gewicht('100'));
        $this->assertSame(75.0, WertParser::gewicht('0.75'));
        $this->assertSame(50.0, WertParser::gewicht('0,5'));
        $this->assertFalse(WertParser::gewicht('150'));
        $this->assertFalse(WertParser::gewicht('abc'));
    }

    #[Test]
    public function gewicht_wendet_die_anteil_heuristik_bei_explizitem_prozentzeichen_nicht_an(): void
    {
        // Ohne «%» wird 0.5 als Anteil gedeutet (→ 50 %), mit «%» ist 0.5 % eindeutig und bleibt so.
        $this->assertSame(50.0, WertParser::gewicht('0.5'));
        $this->assertSame(0.5, WertParser::gewicht('0.5%'));
        $this->assertSame(0.5, WertParser::gewicht('0,5%'));
    }

    #[Test]
    public function gewicht_mehrdeutig_nur_ohne_prozentzeichen_und_wert_bis_1(): void
    {
        $this->assertTrue(WertParser::gewichtMehrdeutig('0.5'));
        $this->assertTrue(WertParser::gewichtMehrdeutig('1'));
        $this->assertFalse(WertParser::gewichtMehrdeutig('50%'));
        $this->assertFalse(WertParser::gewichtMehrdeutig('50'));
        $this->assertFalse(WertParser::gewichtMehrdeutig(''));
        $this->assertFalse(WertParser::gewichtMehrdeutig('abc'));
    }
}
