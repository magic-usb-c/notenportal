<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Werte aus Notenlisten lesen: Datum (d.m.Y, Excel-Seriennummer, ISO), Note (Dezimalkomma, 0.05-Schritte),
 * Gewicht (Prozent oder Anteil). Einzige Regelquelle für diese Formate – von NotenImport::vorschau und
 * Spaltenerkennung genutzt. Zustandslos, deshalb statisch.
 */
final class WertParser
{
    public static function datum(string $s): ?string
    {
        $s = trim(preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $s) ?? '');
        if ($s === '') {
            return null;
        }
        if (is_numeric($s) && (float) $s > 20000 && (float) $s < 80000) {
            return Date::excelToDateTimeObject((float) $s)->format('Y-m-d');
        }
        foreach (['d.m.Y', 'j.n.Y', 'd.m.y', 'j.n.y', 'Y-m-d', 'd/m/Y', 'j/n/Y'] as $format) {
            $d = \DateTime::createFromFormat('!'.$format, $s);
            if ($d && $d->format($format) === $s) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * True, wenn Tag und Monat vertauscht ebenfalls ein gültiges Datum ergäben (Schrägstrich-Format, z. B. aus
     * US-Exporten): «03/04/2026» kann 3. April oder US-Schreibweise 4. März meinen. Punkt-Format gilt als
     * eindeutig Schweizer d.m.Y.
     */
    public static function datumMehrdeutig(string $s): bool
    {
        $s = trim(preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $s) ?? '');
        if (! preg_match('#^(\d{1,2})/(\d{1,2})/\d{2,4}$#', $s, $m)) {
            return false;
        }

        return (int) $m[1] !== (int) $m[2] && (int) $m[1] <= 12 && (int) $m[2] <= 12;
    }

    public static function note(string $s): ?float
    {
        $s = str_replace(',', '.', trim($s));
        if (! is_numeric($s)) {
            return null;
        }
        // Erst am Rohwert prüfen, sonst würde z. B. 0.999 auf 1.0 gerundet fälschlich als gültig durchgehen.
        $roh = (float) $s;
        if ($roh < 1 || $roh > 6) {
            return null;
        }
        $wert = round($roh, 2);

        return abs($wert * 20 - round($wert * 20)) < 1e-6 ? $wert : null;
    }

    /**
     * null = leer (Standard 100), false = ungültig; Werte ≤ 1 gelten als Anteil (0.5 → 50 %) – aber nur ohne
     * explizites «%»-Zeichen, sonst ist die Deutung als Prozentwert eindeutig (0.5 % bleibt 0.5 %).
     */
    public static function gewicht(string $s): float|false|null
    {
        $roh = trim($s);
        $prozent = str_contains($roh, '%');
        $s = str_replace([',', '%'], ['.', ''], $roh);
        if ($s === '') {
            return null;
        }
        if (! is_numeric($s)) {
            return false;
        }
        $wert = (float) $s;
        $wert = ! $prozent && $wert > 0 && $wert <= 1 ? $wert * 100 : $wert;

        return $wert >= 0 && $wert <= 100 ? round($wert, 2) : false;
    }

    /**
     * True, wenn eine Zahl ≤ 1 ohne «%»-Zeichen still als Anteil gedeutet wurde (0.5 → 50 %) statt als
     * (unwahrscheinlicher, aber möglicher) Prozentwert 0.5 %.
     */
    public static function gewichtMehrdeutig(string $s): bool
    {
        $s = trim($s);
        if ($s === '' || str_contains($s, '%')) {
            return false;
        }
        $n = str_replace(',', '.', $s);
        if (! is_numeric($n)) {
            return false;
        }
        $wert = (float) $n;

        return $wert > 0 && $wert <= 1;
    }
}
