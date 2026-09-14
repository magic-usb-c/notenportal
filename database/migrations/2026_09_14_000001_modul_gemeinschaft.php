<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module werden gemeinsame Stammdaten: Jede angemeldete Person darf ein fehlendes Modul anlegen und
 * ein bestehendes ergänzen. Es bleibt bei EINEM Datensatz je Modul – nichts wird je Lernendem kopiert,
 * alle sehen dieselbe Beschreibung, dieselben Ziele und dieselben Dokumente.
 *
 * `erstellt_von_benutzer_id` hält fest, wer ein Modul angelegt hat (Katalogmodule haben niemanden),
 * `link` nimmt einen eigenen Verweis auf – etwa die Modulseite der Schule; der Verweis in den
 * Modulbaukasten entsteht weiterhin aus Nummer und Version (App\Support\Modulbaukasten).
 *
 * Modul-PDFs liegen privat unter storage/app/private/module/{modul_id}/{uuid}.{endung};
 * Zugriff nur über den Controller, nie über die Web-Wurzel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module', function (Blueprint $table) {
            $table->string('link', 500)->nullable()->after('beschreibung');
            $table->unsignedInteger('erstellt_von_benutzer_id')->nullable()->after('link');

            $table->foreign('erstellt_von_benutzer_id', 'fk_module_ersteller')
                ->references('benutzer_id')->on('benutzer')->nullOnDelete();
        });

        Schema::create('modul_dokumente', function (Blueprint $table) {
            $table->increments('modul_dokument_id');
            $table->unsignedInteger('modul_id');
            $table->string('titel', 150);
            $table->string('originalname', 255);
            $table->string('pfad', 255)->unique();
            $table->string('mime', 100);
            $table->unsignedInteger('groesse');
            $table->char('sha256', 64);
            $table->unsignedInteger('hochgeladen_von_benutzer_id')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->index('modul_id', 'idx_modul_dokumente_modul');
            $table->foreign('modul_id', 'fk_modul_dokumente_modul')
                ->references('modul_id')->on('module')->cascadeOnDelete();
            $table->foreign('hochgeladen_von_benutzer_id', 'fk_modul_dokumente_benutzer')
                ->references('benutzer_id')->on('benutzer')->nullOnDelete();
        });

        DB::statement('ALTER TABLE modul_dokumente ADD CONSTRAINT chk_modul_dokumente_groesse CHECK (groesse > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('modul_dokumente');

        Schema::table('module', function (Blueprint $table) {
            $table->dropForeign('fk_module_ersteller');
            $table->dropColumn(['link', 'erstellt_von_benutzer_id']);
        });
    }
};
