<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Betrieb\SicherungKopie;
use Illuminate\Console\Command;

/** Sicherungen ausser Haus spiegeln (läuft nach jeder täglichen Sicherung; manuell als www-data ausführen). */
class SicherungKopieren extends Command
{
    protected $signature = 'notenportal:sicherung-kopieren';

    protected $description = 'Sicherungen per rsync in den Ziel-Ordner oder auf den SSH-Server spiegeln';

    public function handle(SicherungKopie $kopie): int
    {
        if (! $kopie->aktiv()) {
            $this->components->warn('Keine Kopie ausser Haus eingerichtet (Admin → Betrieb).');

            return self::SUCCESS;
        }
        try {
            $kopie->kopieren();
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Sicherungen kopiert.');

        return self::SUCCESS;
    }
}
