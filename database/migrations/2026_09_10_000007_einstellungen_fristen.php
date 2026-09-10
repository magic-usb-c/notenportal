<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Standardfristen als Einstellungen (Admin kann sie ändern): Inaktivität und Vorwarnung Lehrende.
 */
return new class extends Migration
{
    private const array WERTE = ['frist_inaktiv_tage' => '30', 'frist_lehrende_tage' => '60'];

    public function up(): void
    {
        foreach (self::WERTE as $schluessel => $wert) {
            DB::table('einstellungen')->insertOrIgnore(['schluessel' => $schluessel, 'wert' => $wert]);
        }
    }

    public function down(): void
    {
        DB::table('einstellungen')->whereIn('schluessel', array_keys(self::WERTE))->delete();
    }
};
