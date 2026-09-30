<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teil eines Notenbaums, der für Lernende mit einem bestimmten Track entfällt (z. B. Allgemeinbildung
 * im QV, wenn die Berufsmaturität besucht wird). Die übrigen Gewichte der Gruppe werden hochgerechnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notenbaum_knoten', function (Blueprint $table) {
            $table->string('entfaellt_mit_track', 10)->nullable()->after('zaehlt');
        });
    }

    public function down(): void
    {
        Schema::table('notenbaum_knoten', function (Blueprint $table) {
            $table->dropColumn('entfaellt_mit_track');
        });
    }
};
