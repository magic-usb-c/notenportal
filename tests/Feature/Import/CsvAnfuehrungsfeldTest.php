<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\Import\TabellenLeser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Funktionsprüfung Runde 10: Zeilenumbruch in einem Anführungsfeld der CSV darf die Zeile nicht zerreissen. */
class CsvAnfuehrungsfeldTest extends TestCase
{
    private const KOPF = 'Datum;Fach/Modul;Titel;Note;Gewicht';

    #[Test]
    public function zeilenumbruch_im_anfuehrungsfeld_bleibt_eine_zeile(): void
    {
        $zeilen = (new TabellenLeser)->csv(self::KOPF."\n21.10.2026;Deutsch;\"Zeile mit\nUmbruch\";4,5;50\n22.10.2026;Deutsch;Test;5;50\n");

        $this->assertCount(3, $zeilen);
        $this->assertSame(['21.10.2026', 'Deutsch', "Zeile mit\nUmbruch", '4,5', '50'], $zeilen[1]);
        $this->assertSame(['22.10.2026', 'Deutsch', 'Test', '5', '50'], $zeilen[2]);
    }

    #[Test]
    public function windows_zeilenumbruch_im_anfuehrungsfeld_bleibt_eine_zeile(): void
    {
        $zeilen = (new TabellenLeser)->csv(self::KOPF."\r\n21.10.2026;Deutsch;\"Zeile mit\r\nUmbruch\";4,5;50\r\n22.10.2026;Deutsch;Test;5;50\r\n");

        $this->assertCount(3, $zeilen);
        $this->assertSame('4,5', $zeilen[1][3]);
        $this->assertSame(['22.10.2026', 'Deutsch', 'Test', '5', '50'], $zeilen[2]);
    }

    #[Test]
    public function doppelte_anfuehrungszeichen_und_trenner_im_feld_bleiben_erhalten(): void
    {
        $zeilen = (new TabellenLeser)->csv("a;\"sagt \"\"hi\"\"; dann\nweiter\";4\nb;c;5\n");

        $this->assertSame([['a', "sagt \"hi\"; dann\nweiter", '4'], ['b', 'c', '5']], $zeilen);
    }

    #[Test]
    public function einzelnes_anfuehrungszeichen_mitten_im_feld_verschluckt_keine_folgezeilen(): void
    {
        $zeilen = (new TabellenLeser)->csv("a;Bildschirm 24\" Test;4\nb;Zoll 27\";5\nc;d;6\n");

        $this->assertSame([['a', 'Bildschirm 24" Test', '4'], ['b', 'Zoll 27"', '5'], ['c', 'd', '6']], $zeilen);
    }

    #[Test]
    public function nicht_geschlossenes_anfuehrungszeichen_verschluckt_nicht_die_ganze_datei(): void
    {
        $inhalt = "a;\"offen;4\n".str_repeat("x;y;5\n", 40000)."z;1;6\n";

        $zeilen = (new TabellenLeser)->csv($inhalt);

        $this->assertSame(['a', 'offen;4'], $zeilen[0]);
        $this->assertSame(['x', 'y', '5'], $zeilen[1]);
        $this->assertCount(TabellenLeser::MAX_ZEILEN + 21, $zeilen);
    }

    #[Test]
    public function mac_zeilenende_und_leerzeilen_bleiben_erkannt(): void
    {
        $zeilen = (new TabellenLeser)->csv("a;b\r\r;;\rc;\"d\re\"\r");

        $this->assertSame([['a', 'b'], ['c', "d\ne"]], $zeilen);
    }
}
