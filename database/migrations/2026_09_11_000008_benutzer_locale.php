<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Sprache (de/en, null = Standard des Betriebs), App\Http\Middleware\SetLocale.
 * Die Betriebs-Einstellungen sprache_standard und sprachwahl_aktiv liegen in «einstellungen» (Schlüssel/Wert),
 * dafür braucht es keine Schemaänderung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('kontrast');
        });
    }

    public function down(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
