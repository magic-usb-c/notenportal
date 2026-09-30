<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auswertung\Notenbaum\Baum;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Notenbäume auf der Kommandozeile: mitgelieferte Vorlagen auflisten und laden, eigene Dateien
 * importieren, bestehende Bäume exportieren. Die Regeln stehen in BaumVorlage – dieselben wie in den
 * Stammdaten der Oberfläche.
 */
#[Description('Notenbäume auflisten, aus Vorlagen laden, importieren und exportieren')]
#[Signature('notenportal:notenbaum
    {aktion : liste | laden | import | export}
    {ziel? : Vorlage (laden), Datei (import) oder Zieldatei (export, «-» für stdout)}
    {--lehrberuf= : Kürzel oder Name des Lehrberufs (bei Vorlagen für einen Lehrberuf)}
    {--baum= : baum_id für den Export}')]
class NotenbaumVorlage extends Command
{
    public function handle(BaumVorlage $vorlagen): int
    {
        try {
            return match ($this->argument('aktion')) {
                'liste' => $this->liste(),
                'laden' => $this->importieren($vorlagen, BaumVorlage::laden($this->ziel()), $this->ziel()),
                'import' => $this->importieren($vorlagen, BaumVorlage::lesen($this->dateiLesen()), null),
                'export' => $this->exportieren($vorlagen),
                default => $this->fehler('Unbekannte Aktion. Möglich: liste, laden, import, export.'),
            };
        } catch (RuntimeException $e) {
            return $this->fehler($e->getMessage());
        }
    }

    private function liste(): int
    {
        $this->table(['Vorlage', 'Name', 'Bezug'], collect(BaumVorlage::mitgeliefert())
            ->map(fn ($v, $k) => [$k, $v['name'], $v['bezug']])->values()->all());

        $baeume = DB::table('notenbaeume as b')->leftJoin('lehrberufe as l', 'l.lehrberuf_id', '=', 'b.lehrberuf_id')
            ->orderByDesc('b.aktiv')->orderBy('b.baum_id')
            ->get(['b.baum_id', 'b.name', 'b.aktiv', 'b.track_typ', 'l.name as lehrberuf']);
        if ($baeume->isNotEmpty()) {
            $this->table(['ID', 'Name', 'Für', 'Aktiv'], $baeume->map(fn ($b) => [
                $b->baum_id, $b->name, $b->lehrberuf ?? $b->track_typ, $b->aktiv ? 'ja' : 'nein',
            ])->all());
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $d */
    private function importieren(BaumVorlage $vorlagen, array $d, ?string $schluessel): int
    {
        $lehrberuf = null;
        if (($d['bezug'] ?? Baum::LEHRBERUF) === Baum::LEHRBERUF) {
            $angabe = trim((string) $this->option('lehrberuf'));
            if ($angabe === '') {
                return $this->fehler('Diese Vorlage gilt für einen Lehrberuf: --lehrberuf=<Kürzel oder Name> angeben.');
            }
            $lehrberuf = DB::table('lehrberufe')->where('kuerzel', $angabe)->orWhere('name', $angabe)->value('lehrberuf_id');
            if ($lehrberuf === null) {
                return $this->fehler("Lehrberuf «{$angabe}» nicht gefunden.");
            }
        }

        $id = $vorlagen->importieren($d, $lehrberuf === null ? null : (int) $lehrberuf, $schluessel);
        $this->info("Notenbaum «{$d['name']}» angelegt und aktiviert (ID {$id}).");

        return self::SUCCESS;
    }

    private function exportieren(BaumVorlage $vorlagen): int
    {
        $baum = (int) $this->option('baum');
        if ($baum <= 0) {
            return $this->fehler('Für den Export --baum=<ID> angeben (IDs zeigt «liste»).');
        }
        $json = json_encode($vorlagen->exportieren($baum), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ziel = (string) ($this->argument('ziel') ?? storage_path("app/notenbaum-{$baum}.json"));
        if ($ziel === '-') {
            $this->line((string) $json);

            return self::SUCCESS;
        }
        if (@file_put_contents($ziel, $json."\n") === false) {
            return $this->fehler("Datei nicht schreibbar: {$ziel}");
        }
        $this->info("Notenbaum {$baum} geschrieben: {$ziel}");

        return self::SUCCESS;
    }

    private function ziel(): string
    {
        return (string) ($this->argument('ziel') ?? throw new RuntimeException('Welche Vorlage? Die Namen zeigt «liste».'));
    }

    private function dateiLesen(): string
    {
        $datei = $this->ziel();
        $inhalt = is_file($datei) ? @file_get_contents($datei) : false;

        return $inhalt === false ? throw new RuntimeException("Datei nicht lesbar: {$datei}") : $inhalt;
    }

    private function fehler(string $text): int
    {
        $this->error($text);

        return self::FAILURE;
    }
}
