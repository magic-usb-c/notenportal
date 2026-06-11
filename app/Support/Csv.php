<?php

namespace App\Support;

class Csv
{
    /**
     * Schützt vor CSV-/Excel-Formel-Injection (CWE-1236): Werte, die mit
     * =, +, -, @, Tab oder CR beginnen, würden von Excel beim Öffnen als
     * Formel interpretiert — ein vorangestelltes Apostroph neutralisiert das.
     */
    public static function safe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
