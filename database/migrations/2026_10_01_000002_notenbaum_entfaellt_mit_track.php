<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // migrate:rollback nimmt diese Migration vor 000001 zurück. Deren Vorprüfung hier wiederholen, sonst
        // ist die Spalte schon weg, wenn 000001 abbricht, und die Einstellung «entfällt mit» verloren.
        $stufen = DB::table('noten')->whereNull('note_wert')->count();
        if ($stufen > 0) {
            throw new RuntimeException("Rollback abgebrochen: {$stufen} Noten mit Stufe (Sport) vorhanden. Zuerst löschen oder in Zahlen umwandeln.");
        }
        $positionen = DB::table('notenbaum_positionen')->count();
        if ($positionen > 0) {
            throw new RuntimeException("Rollback abgebrochen: {$positionen} erfasste Abschlussnoten (Notenbaum) vorhanden. Zuerst sichern und löschen.");
        }

        Schema::table('notenbaum_knoten', function (Blueprint $table) {
            $table->dropColumn('entfaellt_mit_track');
        });
    }
};
