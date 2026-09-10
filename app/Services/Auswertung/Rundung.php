<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

final class Rundung
{
    /** Kaufmännisch auf den Schritt runden (0.5 → halbe Noten); Schritt 0 = ungerundet. */
    public static function auf(?float $wert, float $schritt): ?float
    {
        if ($wert === null) {
            return null;
        }
        if ($schritt <= 0) {
            return round($wert, 4);
        }

        return round(floor($wert / $schritt + 0.5 + 1e-9) * $schritt, 4);
    }

    /** @param  array<int, float>  $werte */
    public static function mittel(array $werte): ?float
    {
        return $werte === [] ? null : array_sum($werte) / count($werte);
    }
}
