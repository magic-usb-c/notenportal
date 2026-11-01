<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use ZipArchive;

/**
 * Liest Notenlisten als Zeilen mit Zellen: Excel/ODS über PhpSpreadsheet (nur Daten, erstes Blatt),
 * CSV mit erkanntem Trennzeichen und Zeichensatz, PDF über den Textlayer (smalot/pdfparser).
 */
final class TabellenLeser
{
    public const int MAX_ZEILEN = 500;

    public const int MAX_SPALTEN = 60;

    /** Summe der entpackten Einträge einer xlsx/ods: Notenlisten liegen weit darunter, Bomben weit darüber. */
    public const int MAX_ENTPACKT_BYTES = 30 * 1024 * 1024;

    /** Gelesen werden die Notenzeilen plus Platz für Titel-, Kopf- und Fusszeilen; eine Zeile mehr zeigt, dass die Quelle länger ist. */
    private const int GELESEN = self::MAX_ZEILEN + 20;

    /** Längster Datensatz einer CSV in Bytes (mit Zeilenumbrüchen in Anführungsfeldern); darüber gilt ein offenes Anführungsfeld als Fehler der Datei. */
    private const int MAX_LOGISCHE_ZEILE = 64 * 1024;

    /** @return list<list<string>> */
    public function lesen(string $pfad, string $endung): array
    {
        return $this->lesenMitHinweis($pfad, $endung)[0];
    }

    /**
     * Wie lesen(), meldet aber zusätzlich, ob die Quelle mehr Zeilen hat, als gelesen werden (dann fehlt der Rest).
     *
     * @return array{0: list<list<string>>, 1: bool}
     */
    public function lesenMitHinweis(string $pfad, string $endung): array
    {
        $zeilen = match (strtolower($endung)) {
            'pdf' => $this->pdf($pfad),
            'csv', 'txt' => $this->csv((string) file_get_contents($pfad)),
            default => $this->tabelle($pfad),
        };

        $zeilen = array_values(array_filter($zeilen, fn (array $z) => implode('', $z) !== ''));

        return [array_slice($zeilen, 0, self::GELESEN), count($zeilen) > self::GELESEN];
    }

    /**
     * CSV als Zeilen mit Zellen. Leere Zeilen (auch «;;;») werden übersprungen, und nach GELESEN + 1 Zeilen mit Inhalt
     * ist Schluss: Speicher und Zeit hängen so nicht von der Zeilenzahl der Datei ab (eine Zeile mehr als GELESEN
     * genügt, damit lesenMitHinweis() meldet, dass die Quelle länger ist).
     *
     * Ein Zeilenumbruch in einem Anführungsfeld gehört zum Feld (fgetcsv); ein Anführungszeichen mitten in einem
     * Feld («Bildschirm 24" Test») öffnet kein Feld. Ein Feld, das mit «"» beginnt und nie geschlossen wird, reicht
     * höchstens MAX_LOGISCHE_ZEILE Bytes weit; danach gilt nur die erste Zeile als Datensatz und der Rest wird normal gelesen.
     *
     * @return list<list<string>>
     */
    public function csv(string $inhalt): array
    {
        $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt) ?? $inhalt;
        if (! mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }
        // Alle Zeilenenden (\r\n, \r, \n, NEL, LS, PS …) zu \n, damit fgetcsv überall gleich trennt
        $inhalt = preg_replace('/\R/u', "\n", $inhalt) ?? $inhalt;

        // Trennzeichen aus den ersten zehn Zeilen mit Inhalt, ohne die Datei zu zerlegen
        $probe = '';
        $n = 0;
        foreach ($this->csvZeilen($inhalt) as $zeile) {
            if (trim($zeile) === '') {
                continue;
            }
            $probe .= $zeile."\n";
            if (++$n >= 10) {
                break;
            }
        }
        $trenner = collect([';' => substr_count($probe, ';'), ',' => substr_count($probe, ','), "\t" => substr_count($probe, "\t")])->sortDesc()->keys()->first();

        $out = [];
        foreach ($this->csvDatensaetze($inhalt, $trenner) as $zellen) {
            $zellen = array_map(fn ($c) => trim((string) $c), $zellen);
            if (implode('', $zellen) === '') {
                continue;
            }
            $out[] = $zellen;
            if (count($out) > self::GELESEN) {
                break;
            }
        }

        return $out;
    }

    /**
     * Datensätze eines CSV-Textes (Zeilenende \n) nacheinander, ohne den ganzen Text in ein Array zu zerlegen.
     * Leere Zeilen kommen als [null].
     *
     * @return \Generator<int, list<string|null>>
     */
    private function csvDatensaetze(string $inhalt, string $trenner): \Generator
    {
        $strom = fopen('php://temp', 'r+');
        if ($strom === false) {
            throw new RuntimeException('CSV nicht lesbar.');
        }
        try {
            fwrite($strom, $inhalt);
            rewind($strom);
            while (true) {
                $start = (int) ftell($strom);
                $zellen = fgetcsv($strom, null, $trenner, '"', '');
                if ($zellen === false) {
                    return;
                }
                if ((int) ftell($strom) - $start > self::MAX_LOGISCHE_ZEILE) {
                    // Anführungsfeld ohne Ende: nur die erste Zeile gilt als Datensatz, der Rest wird wieder normal gelesen
                    fseek($strom, $start);
                    $zellen = str_getcsv((string) fgets($strom), $trenner, '"', '');
                }
                yield $zellen;
            }
        } finally {
            fclose($strom);
        }
    }

    /**
     * Zeilen eines Textes nacheinander (Zeilenende \n), ohne den ganzen Text in ein Array zu zerlegen; nur für die Trennerprobe.
     *
     * @return \Generator<int, string>
     */
    private function csvZeilen(string $inhalt): \Generator
    {
        $laenge = strlen($inhalt);
        $pos = 0;
        while ($pos < $laenge) {
            $ende = strpos($inhalt, "\n", $pos);
            if ($ende === false) {
                $ende = $laenge;
            }
            yield substr($inhalt, $pos, $ende - $pos);
            $pos = $ende + 1;
        }
    }

    /** @return list<list<string>> */
    private function tabelle(string $pfad): array
    {
        $format = IOFactory::identify($pfad);
        if (in_array($format, ['Xlsx', 'Ods'], true)) {
            $this->entpackteGroessePruefen($pfad);
        }
        $reader = IOFactory::createReader($format);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter($this->bereichsfilter());
        $blatt = $reader->load($pfad)->getSheet(0);

        $letzteSpalte = Coordinate::stringFromColumnIndex(min(Coordinate::columnIndexFromString($blatt->getHighestDataColumn()), self::MAX_SPALTEN));
        // Formeln aus fremden Dateien nie berechnen – den in der Datei gespeicherten Wert nehmen
        $zeilen = [];
        foreach ($blatt->getRowIterator(1, min($blatt->getHighestDataRow(), self::GELESEN + 1)) as $zeile) {
            $zellen = [];
            foreach ($zeile->getCellIterator('A', $letzteSpalte) as $zelle) {
                $v = $zelle->isFormula() ? $zelle->getOldCalculatedValue() : $zelle->getValue();
                $zellen[] = $v === null ? '' : trim(is_float($v) ? rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') : (string) $v);
            }
            $zeilen[] = $zellen;
        }

        return $zeilen;
    }

    /**
     * Eine xlsx/ods ist ein Zip; ein kleines Archiv kann zu Gigabytes XML entpacken. Die Summe der
     * entpackten Grössen steht im Verzeichnis des Archivs und lässt sich prüfen, ohne etwas zu entpacken.
     */
    private function entpackteGroessePruefen(string $pfad): void
    {
        $zip = new ZipArchive;
        if ($zip->open($pfad, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Archiv nicht lesbar.');
        }
        try {
            $summe = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $summe += (int) ($zip->statIndex($i)['size'] ?? 0);
                if ($summe > self::MAX_ENTPACKT_BYTES) {
                    throw new RuntimeException('Archiv entpackt zu gross.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** Nur den Bereich laden, den tabelle() ohnehin auswertet. */
    private function bereichsfilter(): IReadFilter
    {
        return new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= TabellenLeser::MAX_ZEILEN + 21
                    && Coordinate::columnIndexFromString($columnAddress) <= TabellenLeser::MAX_SPALTEN;
            }
        };
    }

    /** @return list<list<string>> */
    private function pdf(string $pfad): array
    {
        return $this->pdfText(PdfLeser::text($pfad));
    }

    /**
     * Text eines PDFs: Schulnetz-Exporte über ihren eigenen Leser, sonst allgemeine Zeilenzerlegung.
     *
     * @return list<list<string>>
     */
    public function pdfText(string $text): array
    {
        return (new Schulnetz)->tabelle($text) ?? $this->textZeilen(str_replace("\x00", '', $text));
    }

    /**
     * Textzeilen in Zellen: Tabulatoren oder mehrere Leerzeichen trennen; Zeilen «Datum Text Note [Gewicht %]» werden zerlegt.
     *
     * @return list<list<string>>
     */
    public function textZeilen(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $zeile) {
            $zeile = trim(preg_replace('/[ \x{00A0}]+/u', ' ', str_replace("\t", '  ', $zeile)) ?? '');
            if ($zeile === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2}\.\d{1,2}\.\d{2,4})\s+(.+?)\s+([1-6](?:[.,]\d{1,2})?)(?:\s+(\d{1,3}(?:[.,]\d+)?)\s*%)?$/u', $zeile, $m)) {
                $out[] = [$m[1], $m[2], '', $m[3], $m[4] ?? ''];

                continue;
            }
            $out[] = array_map('trim', preg_split('/\s{2,}/u', $zeile) ?: [$zeile]);
        }

        return $out;
    }
}
