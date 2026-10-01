<?php

declare(strict_types=1);

namespace App\Support;

final class Zahl
{
    /** 100 → «100 %», 62.5 → «62.5 %» */
    public static function prozent(float|string|null $wert): string
    {
        if ($wert === null || $wert === '') {
            return '–';
        }

        return rtrim(rtrim(number_format((float) $wert, 2, '.', "'"), '0'), '.').' %';
    }

    /** Für Eingabefelder: «100.00» → «100», «33.30» → «33.3» (ohne Tausendertrennung, die das Feld nicht liest) */
    public static function kurz(float|int|string $wert): string
    {
        return is_numeric($wert) ? rtrim(rtrim(number_format((float) $wert, 2, '.', ''), '0'), '.') : (string) $wert;
    }
}
