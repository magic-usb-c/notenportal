<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback ausbauen: Kategorien Fehler/Idee/Frage/Lob (statt Feedback/Idee/Bug), Rollen-Schnappschuss,
 * kurzer Browser-/Betriebssystem-Text, letzte JS-Fehler (max. 3) und optionaler Screenshot
 * (privat auf Disk «local», Auslieferung nur an Admins über den Feedback-Controller).
 *
 * Enum-Wechsel in drei Schritten (erweitern → umschlüsseln → einengen): im Strict-Modus scheitert
 * ein UPDATE auf einen Wert, den das Enum noch nicht kennt. Spalten-Guard, damit ein abgebrochener
 * Lauf (MariaDB-DDL ist nicht transaktional) wiederholt werden kann.
 */
return new class extends Migration
{
    private const ALLE = "'feedback','idee','bug','fehler','frage','lob'";

    public function up(): void
    {
        if (! Schema::hasColumn('feedback', 'rolle')) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->string('rolle', 30)->nullable()->after('benutzer_id');
                $table->string('browser', 150)->nullable()->after('user_agent');
                $table->json('js_fehler')->nullable()->after('viewport');
                $table->string('screenshot_pfad', 255)->nullable()->after('js_fehler');
                $table->string('screenshot_mime', 100)->nullable()->after('screenshot_pfad');
                $table->unsignedInteger('screenshot_groesse')->nullable()->after('screenshot_mime');
            });
        }

        DB::statement('ALTER TABLE feedback MODIFY kategorie ENUM('.self::ALLE.') NOT NULL');
        DB::table('feedback')->where('kategorie', 'bug')->update(['kategorie' => 'fehler']);
        DB::table('feedback')->where('kategorie', 'feedback')->update(['kategorie' => 'frage']);
        DB::statement("ALTER TABLE feedback MODIFY kategorie ENUM('fehler','idee','frage','lob') NOT NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE feedback MODIFY kategorie ENUM('.self::ALLE.') NOT NULL');
        DB::table('feedback')->where('kategorie', 'fehler')->update(['kategorie' => 'bug']);
        DB::table('feedback')->whereIn('kategorie', ['frage', 'lob'])->update(['kategorie' => 'feedback']);
        DB::statement("ALTER TABLE feedback MODIFY kategorie ENUM('feedback','idee','bug') NOT NULL");

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropColumn(['rolle', 'browser', 'js_fehler', 'screenshot_pfad', 'screenshot_mime', 'screenshot_groesse']);
        });
    }
};
