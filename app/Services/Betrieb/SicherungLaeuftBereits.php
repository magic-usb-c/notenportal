<?php

declare(strict_types=1);

namespace App\Services\Betrieb;

use RuntimeException;

/**
 * Die Sperre der Sicherung ist belegt, es läuft also schon eine andere. Kein Fehlschlag der Sicherung,
 * darum meldet der Befehl dafür nichts an die Admins.
 */
final class SicherungLaeuftBereits extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Es läuft bereits eine Sicherung.');
    }
}
