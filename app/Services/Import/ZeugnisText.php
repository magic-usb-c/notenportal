<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Zeugniszeilen «Fach  Semesterwerte  [Ø]» aus dem Textlayer lesen (Zeugnis, Notenportfolio, Schulnetz-Zeugnisnoten).
 * Werte stehen rechts vom Namen: Noten, «d» dispensiert, «b/B» besucht/Sport, «NP» Notenportfolio.
 * Gilt die Note des aktuellen Semesters, nie der Schnitt: hat das Zeugnis eine Ø-Spalte, fällt ein per Tab
 * abgesetzter letzter Wert weg. Durch OCR getrennte Zeilen («Englisch» … «5.0 5.5  5.5») werden wieder verbunden.
 */
final class ZeugnisText
{
    private const string WERT = '/^(?:[1-6](?:[.,]\d{1,4})?|[abcd]|np|va|disp\.?|dispensiert|-{1,4}|–|—)$/iu';

    private const string ZAHL = '/^[1-6](?:[.,]\d{1,4})?$/u';

    /** Zeilen, die nie ein Fach sind (Anfang des kompakten Namens). */
    private const array OHNE = ['durchschnitt', 'gesamt', 'total', 'promotion', 'absenz', 'aktuellerdurchschnitt', 'notendurchschnitt',
        'klassenschnitt', 'klasse', 'bemerkung', 'semester', 'seite', 'bedeutung', 'zeichenerklaerung', 'schnitt', 'mittel'];

    /**
     * @param  bool  $schnittspalte  false = Werte sind reine Semesterspalten (Schulnetz)
     * @return list<array{name: string, note: float, bm: bool}>
     */
    public function zeilen(string $text, bool $schnittspalte = true): array
    {
        $out = [];
        foreach ($this->bloecke($text) as $block) {
            $bm = $this->istBm($block);
            $mitSchnitt = $schnittspalte && $this->hatSchnittspalte($block);
            $offen = null;
            $abstand = 0;

            foreach ($block as $roh) {
                $tokens = $this->tokens($roh);
                if ($tokens === []) {
                    continue;
                }
                $abstand++;
                $j = count($tokens);
                while ($j > 0 && preg_match(self::WERT, $tokens[$j - 1][0])) {
                    $j--;
                }
                $name = trim(implode(' ', array_column(array_slice($tokens, 0, $j), 0)));
                $werte = array_slice($tokens, $j);
                $hatNote = array_filter($werte, fn (array $w) => (bool) preg_match(self::ZAHL, $w[0])) !== [];

                if (! preg_match('/\p{L}/u', $name)) {
                    // reine Wertezeile: gehört zum Fachnamen kurz davor (OCR hat die Zeile getrennt)
                    if ($offen === null || $abstand > 3 || ! $hatNote) {
                        continue;
                    }
                    $name = $offen;
                } elseif ($werte === []) {
                    $offen = preg_match('/^\p{L}[\p{L} \-\/&.]*$/u', $name) && mb_strlen($name) >= 3 && ! $this->ohne($name) ? $name : null;
                    $abstand = 0;

                    continue;
                } elseif (! $hatNote) {
                    $offen = null;

                    continue;
                }
                $offen = null;

                if ($this->ohne($name) || preg_match('/\d{1,2}\.\d{1,2}\.\d{2,4}/u', $name)) {
                    continue;
                }
                $letzter = end($werte);
                if ($mitSchnitt && count($werte) >= 2 && $letzter[1] && preg_match(self::ZAHL, $letzter[0])) {
                    array_pop($werte);
                }
                $note = $this->note((string) end($werte)[0]);
                if ($note !== null) {
                    $out[] = ['name' => $name, 'note' => $note, 'bm' => $bm];
                }
            }
        }

        return $out;
    }

    public static function kompakt(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ç' => 'c', 'ô' => 'o', 'î' => 'i', 'ï' => 'i', 'ù' => 'u', 'û' => 'u']);

        return preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
    }

    /**
     * Mehrere Zeugnisse in einer Datei (Berufsfachschule, BM, Notenportfolio) getrennt lesen.
     *
     * @return list<list<string>>
     */
    private function bloecke(string $text): array
    {
        $bloecke = [[]];
        foreach (preg_split('/\R/u', str_replace("\x00", '', $text)) ?: [] as $zeile) {
            $k = self::kompakt($zeile);
            if ($k === 'zeugnis' || str_starts_with($k, 'notenportfolio')) {
                $bloecke[] = [];
            }
            $bloecke[count($bloecke) - 1][] = $zeile;
        }

        return array_values(array_filter($bloecke));
    }

    /** BM-Zeugnis: Gesamtnote und Promotionsentscheid stehen nur dort. */
    private function istBm(array $block): bool
    {
        foreach ($block as $zeile) {
            $k = self::kompakt($zeile);
            if (str_starts_with($k, 'promotionsentscheid') || str_starts_with($k, 'gesamtnote')) {
                return true;
            }
        }

        return false;
    }

    /** Semesterkopf «1 2 3 4 5 6 7 8 Ø» (OCR liest Ø als 0 oder O). */
    private function hatSchnittspalte(array $block): bool
    {
        foreach ($block as $zeile) {
            if (preg_match('/(?:^|\s)1\s+2\s+3\s+4\s+5\s+6(?:\s+7)?(?:\s+8)?\s+[0OØ]{1,2}\s*$/u', $zeile)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{0: string, 1: bool}> Wort und «stand nach einem Tabulator» */
    private function tokens(string $zeile): array
    {
        $zeile = preg_replace(['/[.·_…]{2,}/u', '/[ \x{00A0}]+/u'], ["\t", ' '], $zeile) ?? '';
        $tokens = [];
        $tab = false;
        foreach (preg_split('/(\s+)/u', trim($zeile), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $teil) {
            if (trim($teil) === '') {
                $tab = str_contains($teil, "\t");

                continue;
            }
            $tokens[] = [$teil, $tab];
            $tab = false;
        }

        return $tokens;
    }

    private function ohne(string $name): bool
    {
        if (preg_match('/^cal[A-Z]/u', $name)) {
            return true; // Schulnetz: berechnete Durchschnitte (calDSA, calDSB)
        }
        $k = self::kompakt($name);
        foreach (self::OHNE as $wort) {
            if (str_starts_with($k, $wort)) {
                return true;
            }
        }

        return false;
    }

    private function note(string $s): ?float
    {
        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) {
            return null;
        }
        $wert = round((float) $s, 2);

        return $wert >= 1 && $wert <= 6 && abs($wert * 20 - round($wert * 20)) < 1e-6 ? $wert : null;
    }
}
