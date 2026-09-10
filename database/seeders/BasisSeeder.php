<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Was jede Installation braucht: Rollen und Notenkategorien.
 * Idempotent – bestehende Einträge bleiben unverändert.
 */
class BasisSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Admin', 'Berufsbildner', 'Lernender'] as $name) {
            $this->einfuegenFallsFehlend('rollen', ['name' => $name]);
        }

        $kategorien = [
            ['code' => 'FACH', 'name' => 'Fachunterricht', 'sortierung' => 10],
            ['code' => 'UEK', 'name' => 'ÜK', 'sortierung' => 20],
            ['code' => 'BMS', 'name' => 'BMS', 'sortierung' => 30],
            ['code' => 'ABU', 'name' => 'ABU', 'sortierung' => 40],
        ];

        foreach ($kategorien as $kategorie) {
            $this->einfuegenFallsFehlend('kategorien', ['code' => $kategorie['code']], $kategorie + ['aktiv' => 1]);
        }
    }

    private function einfuegenFallsFehlend(string $tabelle, array $schluessel, array $werte = []): void
    {
        if (! DB::table($tabelle)->where($schluessel)->exists()) {
            DB::table($tabelle)->insert($schluessel + $werte);
        }
    }
}
