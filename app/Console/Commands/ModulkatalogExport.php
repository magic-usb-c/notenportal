<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Export\Katalogexport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Der Gegenpart zu `notenportal:modulkatalog`: schreibt den Katalog dieser Instanz in dasselbe
 * JSON-Format, das der Import liest. Damit lässt sich ein eingelesener Katalog auf eine zweite
 * Instanz mitnehmen, ohne ihn erneut zu ernten – und ohne dass Katalogdaten im Repository liegen
 * (die Rechte dafür stehen in docs/modulkatalog.md).
 *
 * Die Regeln stehen in App\Services\Export\Katalogexport, gemeinsam genutzt mit der Katalogseite
 * in den Stammdaten. Hier steht nur die Bedienung auf der Kommandozeile.
 */
#[Description('Modulkatalog dieser Instanz als JSON schreiben – Gegenstück zu notenportal:modulkatalog')]
#[Signature('notenportal:modulkatalog-export
    {datei? : Zieldatei (Standard: storage/app/modulkatalog-JJJJ-MM-TT.json), «-» schreibt nach stdout}
    {--mit-eigenen : Auch selbst angelegte Module ausgeben, nicht nur die aus dem Katalog}
    {--nur=* : Nur diese Lehrberufe (exakter Name, mehrfach möglich)}')]
class ModulkatalogExport extends Command
{
    public function handle(Katalogexport $export): int
    {
        $daten = $export->daten(
            mitEigenen: (bool) $this->option('mit-eigenen'),
            nur: array_values(array_filter((array) $this->option('nur'))),
        );

        if ($daten['module'] === []) {
            $this->error('Diese Instanz hat keine Module zum Ausgeben.');

            return self::FAILURE;
        }

        $json = json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->error('Der Katalog liess sich nicht als JSON schreiben.');

            return self::FAILURE;
        }

        $ziel = (string) ($this->argument('datei') ?? storage_path('app/'.$export->dateiname()));
        if ($ziel === '-') {
            $this->line($json);

            return self::SUCCESS;
        }

        // Ohne @ macht Laravel aus der Warnung von file_put_contents eine ErrorException mit
        // Stapelspur – hier genügt ein Satz, der sagt, welcher Pfad nicht geht.
        if (@file_put_contents($ziel, $json) === false) {
            $this->error("Datei nicht schreibbar: {$ziel}");

            return self::FAILURE;
        }

        $zuordnungen = array_sum(array_map(fn (array $a): int => count($a['module']), $daten['abschluesse']));
        $this->info(count($daten['module']).' Module, '.count($daten['abschluesse']).' Abschlüsse, '
            ."{$zuordnungen} Zuordnungen → {$ziel}");
        $this->line('Einlesen auf der Zielinstanz: php artisan notenportal:modulkatalog '
            .basename($ziel).' --anwenden');

        return self::SUCCESS;
    }
}
