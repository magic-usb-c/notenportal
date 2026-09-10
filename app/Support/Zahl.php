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
}
