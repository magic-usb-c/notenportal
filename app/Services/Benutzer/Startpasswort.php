<?php

declare(strict_types=1);

namespace App\Services\Benutzer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Erzeugt ein Startpasswort, das Password::defaults() erfüllt.
 * Ohne leicht verwechselbare Zeichen (0/O, 1/l/I), weil es mündlich oder
 * auf Papier weitergegeben wird.
 */
class Startpasswort
{
    private const string BUCHSTABEN = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    private const string ZIFFERN = '23456789';

    public static function erzeugen(int $laenge = 12): string
    {
        do {
            $zeichen = [
                self::BUCHSTABEN[random_int(0, strlen(self::BUCHSTABEN) - 1)],
                self::ZIFFERN[random_int(0, strlen(self::ZIFFERN) - 1)],
            ];

            $alle = self::BUCHSTABEN.self::ZIFFERN;
            while (count($zeichen) < $laenge) {
                $zeichen[] = $alle[random_int(0, strlen($alle) - 1)];
            }

            shuffle($zeichen);
            $passwort = implode('', $zeichen);
        } while (Validator::make(['p' => $passwort], ['p' => Password::defaults()])->fails());

        return $passwort;
    }
}
