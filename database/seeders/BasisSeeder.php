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

        // Promotionsregel der Berufsmaturität (BMV 2025 Art. 16 Abs. 4, docs/auftrag/LAGE.md §4.1): Gesamtnote
        // ≥ 4,0, Minuspunkte ≤ 2,0, höchstens zwei Noten unter 4. Die Migration «notenlogik» setzte sie nur auf
        // bestehende Zeilen – auf einer frischen Installation war die Tabelle da noch leer und die Regel fehlte.
        DB::table('kategorien')->where('code', 'BMS')
            ->whereNull('promotion_min_schnitt')->whereNull('promotion_max_ungenuegend')->whereNull('promotion_max_minuspunkte')
            ->update(['promotion_min_schnitt' => 4.0, 'promotion_max_ungenuegend' => 2, 'promotion_max_minuspunkte' => 2.0]);
    }

    private function einfuegenFallsFehlend(string $tabelle, array $schluessel, array $werte = []): void
    {
        if (! DB::table($tabelle)->where($schluessel)->exists()) {
            DB::table($tabelle)->insert($schluessel + $werte);
        }
    }
}
