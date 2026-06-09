<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracking wann welcher Benutzer eine Note zum ersten Mal gesehen hat.
     * Ermöglicht "Neu"-Badges für Berufsbildner und Lernende.
     */
    public function up(): void
    {
        Schema::create('noten_gesehen', function (Blueprint $table) {
            $table->increments('gesehen_id');
            $table->unsignedInteger('note_id');
            $table->unsignedInteger('viewer_benutzer_id');
            $table->dateTime('gesehen_am')->useCurrent();

            $table->unique(['note_id', 'viewer_benutzer_id'], 'uk_ng_note_viewer');
            $table->index('note_id', 'idx_ng_note');
            $table->index('viewer_benutzer_id', 'idx_ng_viewer');
            $table->index(['viewer_benutzer_id', 'gesehen_am'], 'idx_ng_viewer_seen');

            $table->foreign('note_id', 'fk_ng_note')
                ->references('note_id')->on('noten');
            $table->foreign('viewer_benutzer_id', 'fk_ng_viewer')
                ->references('benutzer_id')->on('benutzer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noten_gesehen');
    }
};
