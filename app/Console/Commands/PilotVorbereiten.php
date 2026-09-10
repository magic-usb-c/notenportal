<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Start Testbetrieb: Konten, Lernende, Betreuungen und Tracks bleiben,
 * alle Testnoten samt Kommentaren, gesehen-Markierungen, Modulbelegungen
 * und Feedback werden gelöscht. Alle Konten müssen ihr Passwort neu setzen.
 */
class PilotVorbereiten extends Command
{
    protected $signature = 'notenportal:pilot-vorbereiten {--ausfuehren : Änderungen wirklich schreiben (sonst nur Vorschau)}';

    protected $description = 'Testdaten für den Start des Testbetriebs bereinigen';

    /** Reihenfolge wegen Fremdschlüsseln. */
    private const TABELLEN = ['noten_gesehen', 'noten_kommentare', 'noten', 'modul_note_gruppen', 'modul_belegungen', 'feedback'];

    public function handle(): int
    {
        $ausfuehren = (bool) $this->option('ausfuehren');

        $zeilen = array_map(fn (string $t) => [$t, DB::table($t)->count()], self::TABELLEN);
        $zeilen[] = ['benutzer → Passwortwechsel', DB::table('benutzer')->whereNull('geloescht_am')->where('passwort_wechsel_noetig', false)->count()];
        $this->table(['Tabelle', $ausfuehren ? 'bereinigt' : 'würde bereinigt'], $zeilen);

        if (! $ausfuehren) {
            $this->info('Vorschau. Mit --ausfuehren wirklich schreiben (vorher Dump erstellen).');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            foreach (self::TABELLEN as $tabelle) {
                DB::table($tabelle)->delete();
            }

            DB::table('benutzer')->whereNull('geloescht_am')->update(['passwort_wechsel_noetig' => true]);
        });

        $this->info('Testbetrieb vorbereitet.');

        return self::SUCCESS;
    }
}
