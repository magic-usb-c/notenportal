<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Betrieb\Sicherung;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Datenbank und Dokumente als ZIP sichern (täglich über den Scheduler)')]
#[Signature('notenportal:sicherung')]
class SicherungErstellen extends Command
{
    public function handle(Sicherung $sicherung): int
    {
        try {
            $this->line('Sicherung erstellt: '.$sicherung->erstellen());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
