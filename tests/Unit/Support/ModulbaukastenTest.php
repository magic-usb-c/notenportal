<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Modulbaukasten;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Support/Modulbaukasten.php: Verweise auf modulbaukasten.ch.
 * Erfundene Modulnummern – der echte Katalog gehört ICT-Berufsbildung Schweiz (docs/modulkatalog.md).
 */
class ModulbaukastenTest extends TestCase
{
    #[Test]
    public function fuehrende_bezeichner_fallen_weg(): void
    {
        $this->assertSame('987', Modulbaukasten::nummerNormalisieren('M987'));
        $this->assertSame('987', Modulbaukasten::nummerNormalisieren('Modul 987'));
        $this->assertSame('654', Modulbaukasten::nummerNormalisieren('ÜK-654'));
        $this->assertSame('654', Modulbaukasten::nummerNormalisieren('uek 654'));
        $this->assertSame('987A', Modulbaukasten::nummerNormalisieren('m987a'));
    }

    #[Test]
    public function was_keine_katalognummer_ist_bleibt_ohne_nummer(): void
    {
        foreach (['', null, 'ABU-1', 'Projektarbeit', '5', '12345', 'M98-7'] as $roh) {
            $this->assertNull(Modulbaukasten::nummerNormalisieren($roh), (string) $roh);
            $this->assertFalse(Modulbaukasten::istKatalogNummer($roh), (string) $roh);
        }
    }

    #[Test]
    public function der_tiefenlink_braucht_nummer_und_version(): void
    {
        $this->assertSame(
            'https://www.modulbaukasten.ch/module/987/2/de-DE',
            Modulbaukasten::modulLink('M987', '2')
        );
        $this->assertSame(
            'https://www.modulbaukasten.ch/module/987/2/en-US',
            Modulbaukasten::modulLink('987', '2', 'en')
        );
    }

    #[Test]
    public function ohne_brauchbare_version_entsteht_kein_link(): void
    {
        // Bewusst kein geratener Link: eine falsche Adresse antwortet dort mit HTTP 200.
        $this->assertNull(Modulbaukasten::modulLink('M987', null));
        $this->assertNull(Modulbaukasten::modulLink('M987', ''));
        $this->assertNull(Modulbaukasten::modulLink('M987', 'V2'));
        $this->assertNull(Modulbaukasten::modulLink('ABU-1', '2'));
    }

    #[Test]
    public function die_pdf_adresse_folgt_dem_muster_nummer_version_de(): void
    {
        $this->assertSame('https://www.modulbaukasten.ch/Module/987_2_DE.pdf', Modulbaukasten::pdfLink('M987', '2'));
        $this->assertNull(Modulbaukasten::pdfLink('M987', ''));
    }
}
