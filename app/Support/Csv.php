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

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Gegenstück zu safe() beim Wiedereinlesen: entfernt das Apostroph, das safe() vor ein Formelzeichen
     * (=, +, -, @, Tab, CR) gesetzt hat. Ein Apostroph vor jedem anderen Zeichen gehört zum Text und bleibt.
     */
    public static function entschaerfen(string $value): string
    {
        return preg_replace('/^\'(?=[=+\-@\t\r])/', '', $value) ?? $value;
    }
}
