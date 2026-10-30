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

    /** @return list<list<string>> */
    public function lesen(string $pfad, string $endung): array
    {
        $zeilen = match (strtolower($endung)) {
            'pdf' => $this->pdf($pfad),
            'csv', 'txt' => $this->csv((string) file_get_contents($pfad)),
            default => $this->tabelle($pfad),
        };

        return array_slice(array_values(array_filter($zeilen, fn (array $z) => implode('', $z) !== '')), 0, self::MAX_ZEILEN + 20);
    }

    /** @return list<list<string>> */
    public function csv(string $inhalt): array
    {
        $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt) ?? $inhalt;
        if (! mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }
        $zeilen = preg_split('/\R/u', $inhalt) ?: [];
        $probe = implode("\n", array_slice($zeilen, 0, 10));
        $trenner = collect([';' => substr_count($probe, ';'), ',' => substr_count($probe, ','), "\t" => substr_count($probe, "\t")])->sortDesc()->keys()->first();

        return array_map(fn (string $z) => array_map(fn ($c) => trim((string) $c), str_getcsv($z, $trenner, '"', '\\')), $zeilen);
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
        foreach ($blatt->getRowIterator(1, min($blatt->getHighestDataRow(), self::MAX_ZEILEN + 20)) as $zeile) {
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
                return $row <= TabellenLeser::MAX_ZEILEN + 20
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
