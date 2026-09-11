<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Darstellung (Theme, Akzentfarbe, Schriftgrösse, Bewegung), erweiterbar ohne
 * neue Migration: App\Support\Darstellung liest/schreibt einzelne Schlüssel im JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->json('praeferenzen')->nullable()->after('kontrast');
        });
    }

    public function down(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->dropColumn('praeferenzen');
        });
    }
};
