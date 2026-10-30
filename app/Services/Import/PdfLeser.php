<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

/**
 * Der eine Weg, ein hochgeladenes PDF zu parsen (Notenimport und Zeugnis-Abgleich). Ohne diese Grenzen
 * entpackt smalot/pdfparser jeden Flate-Stream vollständig: ein 300 KB kleines PDF mit einem 300 MB grossen
 * Stream sprengt das Speicherlimit mit einem Fatal Error, den kein catch fängt.
 *
 *  - Die Dateigrösse wird vor dem Parsen geprüft (der Upload ist ohnehin auf 10 MB begrenzt).
 *  - Jeder Stream wird nur bis zum Entpack-Limit gelesen; echte Textstreams liegen weit darunter.
 *  - Alle Flate-Streams zusammen dürfen entpackt nicht mehr als MAX_ENTPACKT_GESAMT ergeben: das Limit pro
 *    Stream allein hält hunderte Streams knapp darunter nicht auf. Vorab gezählt, stückweise und ohne Puffer.
 *  - Bildinhalte werden nicht behalten: für den Textlayer sind sie ohne Wert.
 */
final class PdfLeser
{
    public const int MAX_DATEI_BYTES = 12 * 1024 * 1024;

    public const int MAX_ENTPACKT_BYTES = 20 * 1024 * 1024;

    public const int MAX_ENTPACKT_GESAMT = 48 * 1024 * 1024;

    /** @throws RuntimeException wenn die Datei zu gross oder nicht lesbar ist */
    public static function text(string $pfad): string
    {
        $groesse = @filesize($pfad);
        if ($groesse === false || $groesse > self::MAX_DATEI_BYTES) {
            throw new RuntimeException('PDF zu gross oder nicht lesbar.');
        }

        $inhalt = (string) file_get_contents($pfad);
        if (self::entpacktGesamt($inhalt) > self::MAX_ENTPACKT_GESAMT) {
            throw new RuntimeException('PDF zu gross oder nicht lesbar.');
        }
        unset($inhalt);

        $config = new Config;
        $config->setDecodeMemoryLimit(self::MAX_ENTPACKT_BYTES);
        $config->setRetainImageContent(false);

        return (new Parser([], $config))->parseFile($pfad)->getText();
    }

    /** Entpackte Grösse aller Flate-Streams, abgebrochen sobald das Gesamtlimit überschritten ist (Nicht-Flate zählt 0). */
    private static function entpacktGesamt(string $inhalt): int
    {
        $summe = 0;
        preg_match_all('/(?<!end)stream\r?\n/', $inhalt, $treffer, PREG_OFFSET_CAPTURE);
        foreach ($treffer[0] as [$marke, $pos]) {
            $start = $pos + strlen($marke);
            $ende = strpos($inhalt, 'endstream', $start);
            if ($ende === false) {
                break;
            }
            $zlib = inflate_init(ZLIB_ENCODING_DEFLATE);
            // 8 KB Eingabe ergeben höchstens rund 8 MB Ausgabe: der Speicher bleibt auch bei einer Bombe klein
            for ($i = $start; $i < $ende; $i += 8192) {
                $stueck = @inflate_add($zlib, substr($inhalt, $i, min(8192, $ende - $i)), ZLIB_SYNC_FLUSH);
                if ($stueck === false) {
                    break;
                }
                $summe += strlen($stueck);
                if ($summe > self::MAX_ENTPACKT_GESAMT) {
                    return $summe;
                }
            }
        }

        return $summe;
    }
}
