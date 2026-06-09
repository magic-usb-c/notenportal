<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurierbare Grenzwerte für Notenauswertungen (geplante Erweiterung).
     * scope_typ definiert die Granularität: GLOBAL > KATEGORIE > FACH > MODUL.
     * Genau eine der FK-Spalten (kategorie_id/fach_id/modul_id) darf gesetzt sein,
     * passend zum scope_typ.
     *
     * HINWEIS: Diese Tabelle ist im aktuellen Stand noch nicht in der Applikation
     * verwendet. Sie ist für eine spätere Auswertungslogik vorgesehen.
     */
    public function up(): void
    {
        Schema::create('bewertungsregeln', function (Blueprint $table) {
            $table->increments('regel_id');
            $table->enum('scope_typ', ['GLOBAL', 'KATEGORIE', 'FACH', 'MODUL']);
            $table->unsignedInteger('kategorie_id')->nullable();
            $table->unsignedInteger('fach_id')->nullable();
            $table->unsignedInteger('modul_id')->nullable();
            $table->decimal('grenzwert_note', 3, 1);
            $table->date('gueltig_ab');
            $table->date('gueltig_bis')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->index(['scope_typ', 'gueltig_ab', 'gueltig_bis'], 'idx_br_scope');
            $table->index('kategorie_id', 'idx_br_kategorie');
            $table->index('fach_id', 'idx_br_fach');
            $table->index('modul_id', 'idx_br_modul');

            $table->foreign('kategorie_id', 'fk_br_kategorie')
                ->references('kategorie_id')->on('kategorien');
            $table->foreign('fach_id', 'fk_br_fach')
                ->references('fach_id')->on('faecher');
            $table->foreign('modul_id', 'fk_br_modul')
                ->references('modul_id')->on('module');
        });

        DB::statement('ALTER TABLE bewertungsregeln ADD CONSTRAINT chk_br_note CHECK (grenzwert_note >= 1.0 AND grenzwert_note <= 6.0)');
        DB::statement('ALTER TABLE bewertungsregeln ADD CONSTRAINT chk_br_datum CHECK (gueltig_bis IS NULL OR gueltig_bis >= gueltig_ab)');
        // Scope-Konsistenz: genau eine FK-Spalte darf gesetzt sein, passend zum scope_typ
        DB::statement("ALTER TABLE bewertungsregeln ADD CONSTRAINT chk_br_scope CHECK (
            scope_typ = 'GLOBAL'    AND kategorie_id IS NULL AND fach_id IS NULL AND modul_id IS NULL OR
            scope_typ = 'KATEGORIE' AND kategorie_id IS NOT NULL AND fach_id IS NULL AND modul_id IS NULL OR
            scope_typ = 'FACH'      AND kategorie_id IS NULL AND fach_id IS NOT NULL AND modul_id IS NULL OR
            scope_typ = 'MODUL'     AND kategorie_id IS NULL AND fach_id IS NULL AND modul_id IS NOT NULL
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('bewertungsregeln');
    }
};
