<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulstatus (Rückmeldung #14): Abgabetermine/Meilensteine je Modul oder Fach nutzen die
 * bestehende Tabelle `pruefungen` statt einer eigenen Tabelle – Datum, Titel, optionale Gewichtung
 * und der Fach/Modul-Bezug (fach_id XOR modul_id) sind dort schon vorhanden. Einzige Ergänzung:
 * `art` unterscheidet Prüfung von Abgabetermin. Begründung: docs/audit-backlog.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pruefungen', 'art')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                // Default 'pruefung': Bestandsdaten sind ausschliesslich geplante/erfasste Prüfungen.
                $table->enum('art', ['pruefung', 'abgabe'])->default('pruefung')->after('titel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pruefungen', 'art')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                $table->dropColumn('art');
            });
        }
    }
};
