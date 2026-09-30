<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Promotionsregel der Berufsmaturität (BMV 2025 Art. 16 Abs. 4, docs/auftrag/LAGE.md §4.1) auch auf
 * bestehenden Installationen: «notenlogik» setzte sie nur, wenn die Kategorie beim Migrieren schon da war,
 * der BasisSeeder nur bei frischer Installation. Datenbanken, die dazwischen entstanden, hatten keine Regel.
 * Eine vom Admin gesetzte Regel (irgendein Wert) bleibt unangetastet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('kategorien')->where('code', 'BMS')
            ->whereNull('promotion_min_schnitt')->whereNull('promotion_max_ungenuegend')->whereNull('promotion_max_minuspunkte')
            ->update(['promotion_min_schnitt' => 4.0, 'promotion_max_ungenuegend' => 2, 'promotion_max_minuspunkte' => 2.0]);
    }

    public function down(): void
    {
        // Datenkorrektur: nichts zurückzunehmen
    }
};
