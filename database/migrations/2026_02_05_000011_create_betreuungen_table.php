<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Betreuungsverhältnisse: ein Berufsbildner betreut einen Lernenden
     * für einen bestimmten Zeitraum (gueltig_von/bis).
     * gueltig_bis = NULL bedeutet aktuell aktiv.
     */
    public function up(): void
    {
        Schema::create('betreuungen', function (Blueprint $table) {
            $table->increments('betreuung_id');
            $table->unsignedInteger('berufsbildner_id');
            $table->unsignedInteger('lernender_id');
            $table->date('gueltig_von');
            $table->date('gueltig_bis')->nullable();

            $table->index('berufsbildner_id', 'idx_betreuung_bb');
            $table->index('lernender_id', 'idx_betreuung_lernender');
            $table->index(['lernender_id', 'gueltig_von', 'gueltig_bis'], 'idx_betreuung_interval');

            $table->foreign('berufsbildner_id', 'fk_betreuung_bb')
                ->references('berufsbildner_id')->on('berufsbildner');
            $table->foreign('lernender_id', 'fk_betreuung_lernender')
                ->references('lernender_id')->on('lernende');
        });

        DB::statement('ALTER TABLE betreuungen ADD CONSTRAINT chk_betreuung_datum CHECK (gueltig_bis IS NULL OR gueltig_bis >= gueltig_von)');
    }

    public function down(): void
    {
        Schema::dropIfExists('betreuungen');
    }
};
