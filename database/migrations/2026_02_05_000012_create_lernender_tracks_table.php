<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks definieren welche Fächergruppe ein Lernender belegt:
     * BMS = Berufsmaturitätsschule, ABU = Allgemeinbildender Unterricht.
     * Bestimmt welche Fächer dem Lernenden im Notenformular angezeigt werden.
     */
    public function up(): void
    {
        Schema::create('lernender_tracks', function (Blueprint $table) {
            $table->increments('lernender_track_id');
            $table->unsignedInteger('lernender_id');
            $table->enum('track_typ', ['BMS', 'ABU']);
            $table->date('start_datum');
            $table->date('end_datum')->nullable();
            $table->unsignedInteger('start_semester_id');
            $table->unsignedInteger('end_semester_id')->nullable();

            $table->unique(['lernender_id', 'track_typ', 'start_datum'], 'uk_ltrack_unique');
            $table->index('start_semester_id', 'fk_ltrack_start_sem');
            $table->index('end_semester_id', 'fk_ltrack_end_sem');
            $table->index(['lernender_id', 'track_typ', 'end_datum'], 'idx_ltrack_active');
            $table->index(['lernender_id', 'start_datum', 'end_datum'], 'idx_ltrack_range');

            $table->foreign('lernender_id', 'fk_ltrack_lernender')
                ->references('lernender_id')->on('lernende');
            $table->foreign('start_semester_id', 'fk_ltrack_start_sem')
                ->references('semester_id')->on('semester');
            $table->foreign('end_semester_id', 'fk_ltrack_end_sem')
                ->references('semester_id')->on('semester');
        });

        DB::statement('ALTER TABLE lernender_tracks ADD CONSTRAINT chk_ltrack_datum CHECK (end_datum IS NULL OR end_datum >= start_datum)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lernender_tracks');
    }
};
