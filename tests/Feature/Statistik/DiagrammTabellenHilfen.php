<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use DOMDocument;
use DOMXPath;

/** Hilfen für Tests, die die Tabelle «Als Tabelle» eines Diagrammbausteins als Dokument lesen. */
trait DiagrammTabellenHilfen
{
    /** Die erste Tabelle nach dem Anker (Attribut des Bausteins) als XPath-Dokument. */
    private function tabelleNach(string $html, string $anker): DOMXPath
    {
        $start = strpos($html, $anker);
        $this->assertNotFalse($start, 'Anker fehlt: '.$anker);
        $this->assertSame(1, preg_match('/<table\b.*?<\/table>/s', substr($html, $start), $treffer), 'Tabelle nach '.$anker.' fehlt');

        $dokument = new DOMDocument;
        libxml_use_internal_errors(true);
        $dokument->loadHTML('<?xml encoding="utf-8"?><body>'.$treffer[0].'</body>');
        libxml_clear_errors();

        return new DOMXPath($dokument);
    }

    /** Die Tabelle trägt eine Beschriftung, und jede Zeile beginnt mit einer Zeilenkopfzelle. */
    private function pruefeZeilenkopf(DOMXPath $tabelle, string $titel): void
    {
        $beschriftung = $tabelle->query('//table/caption');
        $this->assertSame(1, $beschriftung->length, 'Tabelle ohne caption');
        $this->assertSame($titel, trim($beschriftung->item(0)->textContent));

        $zeilen = $tabelle->query('//tbody//tr')->length;
        $this->assertGreaterThan(0, $zeilen);
        $this->assertSame($zeilen, $tabelle->query('//tbody//tr/*[1][self::th and @scope="row"]')->length, 'Erste Zelle jeder Zeile ist kein Zeilenkopf');
        $this->assertSame(0, $tabelle->query('//tbody//tr/*[1][self::td]')->length);
    }
}
