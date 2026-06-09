<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kommentare zu einzelnen Noten (von Lernenden oder Berufsbildnern).
     * Keine Updates/Deletes – Kommentare sind unveränderlich (Audit-Trail).
     */
    public function up(): void
    {
        Schema::create('noten_kommentare', function (Blueprint $table) {
            $table->increments('kommentar_id');
            $table->unsignedInteger('note_id');
            $table->unsignedInteger('autor_benutzer_id');
            $table->text('kommentar_text');
            $table->dateTime('erstellt_am')->useCurrent();

            $table->index('note_id', 'idx_nkom_note');
            $table->index('autor_benutzer_id', 'idx_nkom_autor');
            $table->index(['note_id', 'erstellt_am'], 'idx_nkom_created');

            $table->foreign('note_id', 'fk_nkom_note')
                ->references('note_id')->on('noten');
            $table->foreign('autor_benutzer_id', 'fk_nkom_autor')
                ->references('benutzer_id')->on('benutzer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noten_kommentare');
    }
};
