<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Aktivitaet;
use App\Support\Protokoll;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Löscht Einträge im Aktivitätsprotokoll, die älter sind als Protokoll::AUFBEWAHRUNG_TAGE
 * (täglicher Schedule-Eintrag in routes/console.php). Gibt es die Tabelle noch nicht
 * (Prod vor der Migration), bricht nichts ab – einfach nichts zu tun.
 */
#[Description('Alte Einträge im Aktivitätsprotokoll löschen (älter als 365 Tage)')]
#[Signature('notenportal:aktivitaeten-aufraeumen')]
class AktivitaetenAufraeumen extends Command
{
    public function handle(): int
    {
        if (! Protokoll::verfuegbar()) {
            $this->line('Aktivitätsprotokoll noch nicht eingerichtet – nichts zu tun.');

            return self::SUCCESS;
        }

        $grenze = now()->subDays(Protokoll::AUFBEWAHRUNG_TAGE);
        $anzahl = Aktivitaet::where('erstellt_am', '<', $grenze)->delete();

        $this->line("{$anzahl} Einträge gelöscht (älter als {$grenze->toDateString()}).");

        return self::SUCCESS;
    }
}
