<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * PDF-Exporte «Lernendenübersicht» aus Schulnetz (Textlayer über smalot/pdfparser):
 * «Aktuelle Noten» (Kurse mit Einzelprüfungen), «Zeugnisnoten» (Kurs × Semester) und reine Stammdaten.
 * Liefert eine Tabelle mit Kopfzeile, die NotenImport wie jede andere Notenliste liest.
 */
final class Schulnetz
{
    public const string AKTUELLE_NOTEN = 'Schulnetz · Aktuelle Noten';

    public const string ZEUGNISNOTEN = 'Schulnetz · Zeugnisnoten';

    public const array KOPF = ['Datum', 'Fach/Modul', 'Titel', 'Note', 'Gewicht'];

    /** Kurszeile «158-INXX 99 A-abc» bzw. «ENG-INXX 99 A,INYY 99 B-abc»: Kurs, Klasse(n) mit Jahrgang, Lehrperson. */
    private const string KURS = '/^(?<code>[\p{L}0-9]{1,10})-(?<klasse>[^\t]*\d[^\t]*)-(?<lp>[\p{L}0-9]{2,12})$/u';

    /** @return 'aktuell'|'zeugnis'|'stammdaten'|null */
    public function art(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/^.{0,80}- Aktuelle Noten - /mu', $text),
            (bool) preg_match('/^Kurs\s+Notendurchschnitt/mu', $text) && str_contains($text, 'Einzelprüfungen') => 'aktuell',
            (bool) preg_match('/^Zeugnisnoten - /mu', $text),
            (bool) preg_match('/^\d\. \d{2}\/\d{2} \(\d+\)/mu', $text) && (bool) preg_match('/^\S+ \([rK]\)/mu', $text) => 'zeugnis',
            (bool) preg_match('/^(?:Grunddaten|Erziehungsberechtigtendaten|Lehrverträge) - /mu', $text) => 'stammdaten',
            default => null,
        };
    }

    /**
     * Schulnetz-Text als Tabelle (erste Zeile = Formatkennung, dann Kopf); null = kein Schulnetz-Export.
     *
     * @return list<list<string>>|null
     */
    public function tabelle(string $text): ?array
    {
        $text = str_replace("\x00", '', $text);

        return match ($this->art($text)) {
            'aktuell' => [[self::AKTUELLE_NOTEN], self::KOPF, ...array_map(
                fn (array $p) => [$p['datum'], $p['bezug'], $p['thema'], $p['note'] ?? '', $p['gewicht'] === null ? '' : $this->zahl($p['gewicht'])],
                $this->pruefungen($text),
            )],
            'zeugnis' => [[self::ZEUGNISNOTEN], self::KOPF, ...array_map(
                fn (array $z) => ['', $z['name'], 'Zeugnis', $this->zahl($z['note']), ''],
                (new ZeugnisText)->zeilen($text, false),
            )],
            'stammdaten' => [],
            default => null,
        };
    }

    /**
     * Einzelprüfungen aus «Aktuelle Noten»: Kurs → Fach/Modul, Thema → Titel, Bewertung, Gewichtung in Prozent.
     *
     * @return list<array{kurs: string, bezug: string, datum: string, thema: string, note: ?string, gewicht: ?float}>
     */
    public function pruefungen(string $text): array
    {
        $zeilen = preg_split('/\R/u', str_replace("\x00", '', $text)) ?: [];
        $mitKlassenschnitt = str_contains($text, 'Klassenschnitt');
        $kurse = [];
        $kurs = null;
        $titel = null;

        foreach ($zeilen as $roh) {
            $zeile = trim(preg_replace('/[ \x{00A0}]+/u', ' ', $roh) ?? '');
            if ($zeile === '') {
                continue;
            }
            if ($titel !== null) {
                // Kurstitel läuft bis zur Zeile «Notendurchschnitt Eingesehen» (-- oder 5.000)
                if (preg_match('/^(--|\d+[.,]\d+)(?:\s|$)/u', $zeile, $s)) {
                    $kurse[$kurs]['titel'] = trim(implode(' ', $titel));
                    $kurse[$kurs]['schnitt'] = $s[1] === '--' ? null : (float) str_replace(',', '.', $s[1]);
                    $titel = null;
                } else {
                    $titel[] = $zeile;
                }

                continue;
            }
            if (preg_match(self::KURS, $zeile, $m)) {
                $kurs = count($kurse).'|'.$m['code'];
                $kurse[$kurs] = ['code' => $m['code'], 'titel' => '', 'schnitt' => null, 'pruefungen' => []];
                $titel = [];

                continue;
            }
            if ($kurs !== null && preg_match('/^(?:.*?\s)?(?<datum>\d{1,2}\.\d{1,2}\.\d{4})\s+(?<rest>.+)$/u', $zeile, $m)) {
                $kurse[$kurs]['pruefungen'][] = ['datum' => $m['datum'], ...$this->pruefungsWerte($m['rest'], $mitKlassenschnitt)];
            }
        }

        $out = [];
        foreach ($kurse as $k) {
            $bezug = ctype_digit($k['code']) ? trim($k['code'].' '.$k['titel']) : trim($k['titel'].' ('.$k['code'].')');
            $pruefungen = $this->klaeren($k['pruefungen'], $k['schnitt']);
            foreach ($this->prozent(array_column($pruefungen, 'faktor')) as $i => $gewicht) {
                $p = $pruefungen[$i];
                $out[] = ['kurs' => $k['code'], 'bezug' => $bezug, 'datum' => $p['datum'], 'thema' => $p['thema'], 'note' => $p['note'], 'gewicht' => $gewicht];
            }
        }

        return $out;
    }

    /**
     * «Thema [Bewertung] Gewichtung [Klassenschnitt]» von rechts zerlegen; Trenner (Tab/Leerzeichen) sind im Textlayer nicht verlässlich.
     *
     * @return array{thema: string, note: ?string, faktor: ?float, mehrdeutig: bool, alternativ: string}
     */
    private function pruefungsWerte(string $rest, bool $mitKlassenschnitt): array
    {
        $t = preg_split('/\s+/u', trim($rest)) ?: [];
        if ($mitKlassenschnitt && count($t) > 1 && preg_match('/^(?:-{1,2}|–|\d+(?:[.,]\d+)?)$/u', (string) end($t))) {
            array_pop($t);
        }
        $faktor = null;
        if (count($t) > 1 && preg_match('/^\d+(?:[.,]\d+)?$/u', (string) end($t))) {
            $faktor = (float) str_replace(',', '.', (string) array_pop($t));
        }
        $note = null;
        $mehrdeutig = false;
        if (count($t) > 1 && preg_match('/^(?:[1-6](?:[.,]\d{1,3})?|-{1,2})$/u', (string) end($t))) {
            $note = (string) array_pop($t);
            $mehrdeutig = (bool) preg_match('/^[1-6]$/', $note);
            $note = str_starts_with($note, '-') ? null : str_replace(',', '.', $note);
        }
        $thema = implode(' ', $t);

        return ['thema' => mb_substr($thema, 0, 150), 'note' => $note, 'faktor' => $faktor,
            'mehrdeutig' => $mehrdeutig, 'alternativ' => mb_substr(trim($thema.' '.$note), 0, 150)];
    }

    /**
     * Eine Ganzzahl am Ende des Themas («Test 2») ist nur dann die Bewertung, wenn der Kursschnitt dazu passt;
     * sonst gehört sie zum Thema und die Prüfung ist noch nicht bewertet.
     *
     * @param  list<array<string, mixed>>  $pruefungen
     * @return list<array<string, mixed>>
     */
    private function klaeren(array $pruefungen, ?float $schnitt): array
    {
        $offen = array_keys(array_filter($pruefungen, fn (array $p) => $p['mehrdeutig']));
        if ($offen === [] || count($offen) > 8) {
            return $pruefungen;
        }
        for ($maske = 0; $maske < 2 ** count($offen); $maske++) {
            $variante = $pruefungen;
            foreach ($offen as $bit => $i) {
                if ($maske & (1 << $bit)) {
                    $variante[$i] = [...$variante[$i], 'note' => null, 'thema' => $variante[$i]['alternativ']];
                }
            }
            $s = $this->schnitt($variante);
            if (($s === null && $schnitt === null) || ($s !== null && $schnitt !== null && abs($s - $schnitt) < 0.006)) {
                return $variante;
            }
        }

        return $pruefungen;
    }

    /** @param  list<array<string, mixed>>  $pruefungen */
    private function schnitt(array $pruefungen): ?float
    {
        $summe = 0.0;
        $gewichte = 0.0;
        foreach ($pruefungen as $p) {
            if ($p['note'] !== null) {
                $summe += (float) $p['note'] * ($p['faktor'] ?? 1.0);
                $gewichte += $p['faktor'] ?? 1.0;
            }
        }

        return $gewichte > 0 ? $summe / $gewichte : null;
    }

    /**
     * Schulnetz-Gewichtungen sind Faktoren (0.33333333, 0.5, 1, 2). Anteile, die zusammen 1 ergeben, werden kumuliert
     * gerundet (33.33 + 33.34 + 33.33 = 100), damit ein Modul abgeschlossen ist; Faktoren über 1 werden auf 100 skaliert.
     *
     * @param  list<?float>  $faktoren
     * @return list<?float>
     */
    private function prozent(array $faktoren): array
    {
        $bekannt = array_filter($faktoren, fn ($f) => $f !== null);
        $summe = array_sum($bekannt);
        $max = $bekannt === [] ? 0.0 : max($bekannt);

        $out = [];
        $kumuliert = 0.0;
        foreach ($faktoren as $f) {
            if ($f === null) {
                $out[] = null;

                continue;
            }
            if ($max <= 1 && abs($summe - 1) < 0.02) {
                $vorher = round($kumuliert * 100, 2);
                $kumuliert += $f;
                $out[] = round(round($kumuliert * 100, 2) - $vorher, 2);
            } else {
                $out[] = round($f * ($max > 1 ? 100 / $max : 100), 2);
            }
        }

        return $out;
    }

    private function zahl(float $wert): string
    {
        return rtrim(rtrim(number_format($wert, 2, '.', ''), '0'), '.');
    }
}
