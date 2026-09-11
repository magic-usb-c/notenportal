<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Bereitschaft;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rein lesende Betriebsbereitschaftsprüfung vor dem Go-Live (Logik in App\Support\Bereitschaft).
 * Exit 1, sobald mindestens ein Prüfpunkt "fehler" meldet, sonst 0 (auch bei "warnung").
 */
#[Description('Betriebsbereitschaft rein lesend prüfen (Umgebung, Sicherung, Mail, Kalenderabgleich, Migrationen)')]
#[Signature('notenportal:bereitschaft {--json : Ausgabe als JSON statt Tabelle}')]
class BereitschaftPruefen extends Command
{
    private const array SYMBOL = [
        Bereitschaft::OK => '<fg=green>ok</>',
        Bereitschaft::WARNUNG => '<fg=yellow>warnung</>',
        Bereitschaft::FEHLER => '<fg=red>fehler</>',
    ];

    public function handle(Bereitschaft $bereitschaft): int
    {
        $pruefungen = $bereitschaft->pruefen();
        $bereit = ! collect($pruefungen)->contains(fn (array $p) => $p['status'] === Bereitschaft::FEHLER);

        if ($this->option('json')) {
            $this->line(json_encode(
                ['bereit' => $bereit, 'pruefungen' => $pruefungen],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        } else {
            $this->table(['Prüfpunkt', 'Status', 'Handlungsanweisung'], collect($pruefungen)
                ->map(fn (array $p) => [$p['label'], self::SYMBOL[$p['status']], $p['hinweis']])->all());

            $bereit
                ? $this->components->info('Betriebsbereit (Warnungen möglich, siehe Tabelle).')
                : $this->components->error('Nicht betriebsbereit – mindestens ein Prüfpunkt zeigt «fehler».');
        }

        return $bereit ? self::SUCCESS : self::FAILURE;
    }
}
