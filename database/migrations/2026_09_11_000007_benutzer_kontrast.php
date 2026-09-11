<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Option «Hoher Kontrast» (überschreibt das Betriebs-Theme, App\Support\Theme).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->boolean('kontrast')->default(false)->after('darstellung');
        });
    }

    public function down(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->dropColumn('kontrast');
        });
    }
};
