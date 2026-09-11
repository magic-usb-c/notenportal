<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\Messages\BackupFailed;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Datenbank und Dokumente als ZIP sichern (täglich über den Scheduler)')]
#[Signature('notenportal:sicherung')]
class SicherungErstellen extends Command
{
    public function handle(Sicherung $sicherung, SicherungKopie $kopie): int
    {
        try {
            $this->line('Sicherung erstellt: '.$sicherung->erstellen());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->melden($e->getMessage());

            return self::FAILURE;
        }

        // Kopie ausser Haus (falls eingerichtet); Fehler melden, die lokale Sicherung bleibt gültig
        if ($kopie->aktiv()) {
            try {
                $kopie->kopieren();
                $this->line('Kopie ausser Haus aktualisiert.');
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $this->melden('Kopie ausser Haus: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function melden(string $fehler): void
    {
        foreach (Empfaenger::aktiveAdmins() as $admin) {
            Notifier::send($admin, NotificationCatalog::BACKUP_FAILED, BackupFailed::content($fehler));
        }
    }
}
