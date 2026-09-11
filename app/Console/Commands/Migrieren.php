<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrationen mit dem DDL-Benutzer (DB_MIGRATE_USERNAME/-PASSWORD aus .env) statt dem Web-Benutzer,
 * der im Betrieb nur Datenrechte hat. Funktioniert auch mit gecachter Config.
 */
class Migrieren extends Command
{
    protected $signature = 'notenportal:migrate {--rollback : letzten Schritt zurücknehmen} {--pretend : nur SQL anzeigen} {--path=* : nur diese Migrationsdateien (relativ zum Projekt)}';

    protected $description = 'Migrationen mit dem Migrations-Benutzer ausführen';

    public function handle(): int
    {
        $benutzer = config('notenportal.db_migrate.username');
        $verbindung = (string) config('database.default');

        if (filled($benutzer)) {
            config([
                "database.connections.{$verbindung}.username" => $benutzer,
                "database.connections.{$verbindung}.password" => (string) config('notenportal.db_migrate.password'),
            ]);
            DB::purge($verbindung);
            $this->components->info("Migration als {$benutzer} auf ".config("database.connections.{$verbindung}.database"));
        } else {
            $this->components->warn('DB_MIGRATE_USERNAME fehlt – Migration mit dem Web-Benutzer.');
        }

        return $this->call($this->option('rollback') ? 'migrate:rollback' : 'migrate', array_filter([
            '--force' => true,
            '--pretend' => $this->option('pretend') ?: null,
            '--step' => $this->option('rollback') ? 1 : null,
            '--path' => $this->option('path') ?: null,
        ]));
    }
}
