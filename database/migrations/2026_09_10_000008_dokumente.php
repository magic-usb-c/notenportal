<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumente je Lernender (Zeugnisse, Notenlisten, Sonstiges). Dateien liegen privat unter
 * storage/app/private/lernende/{id}/dokumente/{jahr}/{uuid}.{endung}; Zugriff nur über Controller + Policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumente', function (Blueprint $table) {
            $table->increments('dokument_id');
            $table->unsignedInteger('lernender_id');
            $table->unsignedInteger('semester_id')->nullable();
            $table->enum('art', ['zeugnis', 'notenliste', 'sonstiges'])->default('sonstiges');
            $table->string('titel', 150);
            $table->string('originalname', 255);
            $table->string('pfad', 255)->unique();
            $table->string('mime', 100);
            $table->unsignedInteger('groesse');
            $table->char('sha256', 64);
            $table->unsignedInteger('hochgeladen_von_benutzer_id')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->index(['lernender_id', 'art'], 'idx_dokumente_lernender_art');
            $table->foreign('lernender_id', 'fk_dokumente_lernender')->references('lernender_id')->on('lernende');
            $table->foreign('semester_id', 'fk_dokumente_semester')->references('semester_id')->on('semester');
            $table->foreign('hochgeladen_von_benutzer_id', 'fk_dokumente_benutzer')->references('benutzer_id')->on('benutzer');
        });

        DB::statement('ALTER TABLE dokumente ADD CONSTRAINT chk_dokumente_groesse CHECK (groesse > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumente');
    }
};
