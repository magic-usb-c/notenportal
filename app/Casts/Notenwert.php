<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Noten mit bis zu zwei Nachkommastellen (Viertelnoten wie 4.25).
 * Anzeige ohne überflüssige Null: 4.25, 4.5, 5.0.
 */
class Notenwert implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $wert = number_format((float) $value, 2, '.', '');

        return str_ends_with($wert, '0') ? substr($wert, 0, -1) : $wert;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : number_format(round((float) $value, 2), 2, '.', '');
    }
}
