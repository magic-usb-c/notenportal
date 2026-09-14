<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\Katalogimport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Liest eine Ernte aus `tools/modulkatalog-ernte.mjs` ein: Module mit Katalogversion, Kompetenzfeld,
 * Kompetenz, Objekt, Handlungszielen und LBV-Elementen, dazu die Zuordnung Abschluss → Modul mit
 * Pflichtgrad und Lehrjahr.
 *
 * Die Regeln – Kollision eigener Nummern, getrennte Jahrgänge, Titelschutz – stehen in
 * App\Services\Import\Katalogimport und gelten damit gleich für die Katalogseite in den Stammdaten.
 * Hier steht nur die Bedienung auf der Kommandozeile: Datei lesen, Zahlen zeigen, auf Wunsch schreiben.
 *
 * Rechtslage, Herkunft der Daten und Ablauf: docs/modulkatalog.md.
 */
#[Description('Modulkatalog aus einer Ernte einlesen – Vorschau, mit --anwenden schreiben')]
#[Signature('notenportal:modulkatalog
    {datei : JSON-Datei aus tools/modulkatalog-ernte.mjs}
    {--anwenden : Änderungen wirklich schreiben}
    {--nur=* : Nur diese Abschlüsse (exakter Name, mehrfach möglich)}
    {--ohne-berufe : Keine fehlenden Lehrberufe anlegen, nur bestehende zuordnen}
    {--eigene-uebernehmen : Eigene Module auch dann übernehmen, wenn die Nummer anders gemeint scheint}')]
class ModulkatalogImport extends Command
{
    public function handle(): int
    {
        if (! Katalogimport::bereit()) {
            $this->error('Die Katalogspalten fehlen. Zuerst: php artisan notenportal:migrate');

            return self::FAILURE;
        }

        $pfad = (string) $this->argument('datei');
        if (! is_file($pfad) || ! is_readable($pfad)) {
            $this->error("Datei nicht lesbar: {$pfad}");

            return self::FAILURE;
        }

        $import = new Katalogimport(
            ohneBerufe: (bool) $this->option('ohne-berufe'),
            eigeneUebernehmen: (bool) $this->option('eigene-uebernehmen'),
        );

        try {
            $plan = $import->planen((string) file_get_contents($pfad), (array) $this->option('nur'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (array_slice($plan['fehler'], 0, 10) as $hinweis) {
            $this->warn($hinweis);
        }
        if (count($plan['fehler']) > 10) {
            $this->warn('… '.(count($plan['fehler']) - 10).' weitere Hinweise.');
        }

        $zahlen = $plan['zahlen'];
        $this->line('Ernte vom '.($plan['stand'] ?? 'unbekannt').' aus '.$pfad);
        $this->table(['Was', 'Anzahl'], [
            ['Module neu', (string) $zahlen['module_neu']],
            ['Module aktualisiert', (string) $zahlen['module_alt']],
            ['Handlungsziele', (string) $zahlen['ziele']],
            ['LBV-Elemente', (string) $zahlen['lbv']],
            ['Abschlüsse', (string) $zahlen['abschluesse']],
            ['Lehrberufe neu', (string) $zahlen['berufe_neu']],
            ['Zuordnungen Beruf→Modul', (string) $zahlen['zuordnungen']],
            ['Eigene Module übersprungen', (string) $zahlen['konflikte']],
        ]);

        if ($plan['konflikte'] !== []) {
            $this->warn('Diese Nummern stehen im Portal als eigenes Modul und bleiben unberührt:');
            foreach (array_slice($plan['konflikte'], 0, 10, true) as $nummer => $titel) {
                $this->line("  {$nummer}: {$titel}");
            }
            if (count($plan['konflikte']) > 10) {
                $this->line('  … '.(count($plan['konflikte']) - 10).' weitere.');
            }
            $this->line('Entweder dem eigenen Modul eine eigene Nummer geben (etwa ABU01 statt 801) '
                .'oder mit --eigene-uebernehmen einlesen, wenn es dieselben Module sind.');
        }
        if ($plan['berufe_neu'] !== []) {
            $this->line($this->option('ohne-berufe')
                ? 'Übersprungen (--ohne-berufe): '.implode(', ', $plan['berufe_neu'])
                : 'Neue Lehrberufe: '.implode(', ', $plan['berufe_neu']));
        }
        if ($plan['lernort'] === null) {
            $this->warn('Kein Lernort in `kategorien` – neue Zuordnungen bleiben ohne Lernort.');
        }

        if (! $this->option('anwenden')) {
            $this->info('Nur Vorschau. Schreiben mit --anwenden.');

            return self::SUCCESS;
        }

        $bericht = $import->anwenden($plan);

        $this->info("Geschrieben: {$bericht['module']} Module, {$bericht['ziele']} Handlungsziele, "
            ."{$bericht['lbv']} LBV-Elemente, {$bericht['berufe']} Lehrberufe, {$bericht['zuordnungen']} Zuordnungen.");

        return self::SUCCESS;
    }
}
