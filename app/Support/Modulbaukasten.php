<?php

namespace App\Support;

/**
 * Verweise in den Modulbaukasten von ICT-Berufsbildung Schweiz (modulbaukasten.ch).
 *
 * Ein Verweis entsteht nur, wenn Modulnummer **und** Katalogversion bekannt sind. Ohne Version
 * gibt es keinen Link: die Adresse enthält die Version, und «nicht gefunden» antwortet dort mit
 * HTTP 200 und einer HTML-Seite – ein geratener Link würde also nicht einmal auffallen.
 * Eigene Module (ABU, BMS, schuleigene Nummern) haben im Katalog nichts und bleiben ohne Verweis.
 */
final class Modulbaukasten
{
    public const BASIS = 'https://www.modulbaukasten.ch';

    public const QUELLE = 'modulbaukasten';

    /** Katalognummern sind 2–4 Ziffern mit optionalem Buchstaben am Ende («319», «226A»). */
    private const NUMMER = '/^(\d{2,4}[A-Z]?)$/';

    /**
     * Führende Bezeichner entfernen: «M319», «ÜK-106», «Modul 431» ergeben «319», «106», «431».
     */
    public static function nummerNormalisieren(?string $roh): ?string
    {
        $wert = strtoupper(trim((string) $roh));
        $wert = preg_replace('/^(?:MODUL|ÜK|UEK|BK|M)[\s\-:.]*/u', '', $wert) ?? $wert;
        $wert = trim($wert);

        return preg_match(self::NUMMER, $wert) === 1 ? $wert : null;
    }

    public static function istKatalogNummer(?string $roh): bool
    {
        return self::nummerNormalisieren($roh) !== null;
    }

    /**
     * Tiefenlink auf die Modulseite, z. B. https://www.modulbaukasten.ch/module/319/1/de-DE
     */
    public static function modulLink(?string $nummer, ?string $version, string $sprache = 'de-DE'): ?string
    {
        $nr = self::nummerNormalisieren($nummer);
        $ver = trim((string) $version);
        if ($nr === null || $ver === '' || preg_match('/^\d{1,2}$/', $ver) !== 1) {
            return null;
        }

        return self::BASIS.'/module/'.$nr.'/'.$ver.'/'.($sprache === 'en' ? 'en-US' : 'de-DE');
    }

    /**
     * Adresse der Modulidentifikation als PDF. Achtung: Ältere Module liegen stattdessen unter
     * «{Nummer}_{Version}_{Titel}.pdf», und eine fehlende Datei antwortet mit HTTP 200 text/html.
     * Wer die Datei holt, prüft deshalb den Inhaltstyp, nicht den Status.
     */
    public static function pdfLink(?string $nummer, ?string $version): ?string
    {
        $nr = self::nummerNormalisieren($nummer);
        $ver = trim((string) $version);
        if ($nr === null || $ver === '') {
            return null;
        }

        return self::BASIS.'/Module/'.$nr.'_'.$ver.'_DE.pdf';
    }
}
