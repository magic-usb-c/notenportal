<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine Modulbelegung ist der Zeitraum, in dem ein Lernender ein bestimmtes Modul besucht.
     * end_datum = NULL bedeutet laufend. Wird beim ersten Notenerfassen automatisch erstellt.
     * Der Composite-Unique (modul_belegung_id, lernender_id) dient als zusammengesetzter FK
     * in der noten-Tabelle, um sicherzustellen dass eine Note nur zur Belegung desselben
     * Lernenden gehören kann.
     */
    public function up(): void
    {
        Schema::create('modul_belegungen', function (Blueprint $table) {
            $table->increments('modul_belegung_id');
            $table->unsignedInteger('lernender_id');
            $table->unsignedInteger('modul_id');
            $table->date('start_datum');
            $table->date('end_datum')->nullable();

            $table->unique(['modul_belegung_id', 'lernender_id'], 'uk_mbel_id_lernender');
            $table->index('lernender_id', 'idx_mbel_lernender');
            $table->index('modul_id', 'idx_mbel_modul');
            $table->index(['start_datum', 'end_datum'], 'idx_mbel_period');
            $table->index(['lernender_id', 'end_datum'], 'idx_mbel_active');

            $table->foreign('lernender_id', 'fk_mbel_lernender')
                ->references('lernender_id')->on('lernende');
            $table->foreign('modul_id', 'fk_mbel_modul')
                ->references('modul_id')->on('module');
        });

        DB::statement('ALTER TABLE modul_belegungen ADD CONSTRAINT chk_mbel_datum CHECK (end_datum IS NULL OR end_datum >= start_datum)');
    }

    public function down(): void
    {
        Schema::dropIfExists('modul_belegungen');
    }
};
