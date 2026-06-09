<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kerntabelle für alle Noten.
     *
     * Wichtige Design-Entscheidungen:
     * - fach_id XOR modul_belegung_id: genau eine muss gesetzt sein (CHECK-Constraint).
     * - gruppe_id: optional, nur bei Modulnoten mit Gruppenstruktur.
     * - Composite-FK (modul_belegung_id, lernender_id) → modul_belegungen:
     *   Stellt sicher, dass eine Note nur zu einer Belegung desselben Lernenden gehören kann.
     * - Composite-FK (gruppe_id, modul_belegung_id) → modul_note_gruppen:
     *   Stellt sicher, dass die Gruppe zur selben Belegung gehört wie die Note.
     * - note_wert: 1.0 – 6.0 (Schweizer Notensystem).
     */
    public function up(): void
    {
        Schema::create('noten', function (Blueprint $table) {
            $table->increments('note_id');
            $table->unsignedInteger('lernender_id');
            $table->unsignedInteger('kategorie_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('fach_id')->nullable();
            $table->unsignedInteger('modul_belegung_id')->nullable();
            $table->unsignedInteger('gruppe_id')->nullable();
            $table->string('titel', 150)->nullable();
            $table->date('pruefungsdatum');
            $table->decimal('note_wert', 3, 1);
            $table->decimal('gewichtung_prozent', 6, 2)->nullable();
            $table->unsignedInteger('erfasst_von_benutzer_id');
            $table->unsignedInteger('aktualisiert_von_benutzer_id')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('geloescht_am')->nullable(); // Soft Delete

            // Performance-Indizes
            $table->index(['lernender_id', 'pruefungsdatum'], 'idx_noten_learner_date');
            $table->index('semester_id', 'idx_noten_semester');
            $table->index('kategorie_id', 'idx_noten_kategorie');
            $table->index('fach_id', 'idx_noten_fach');
            $table->index('modul_belegung_id', 'idx_noten_belegung');
            $table->index('gruppe_id', 'idx_noten_gruppe');
            $table->index(['gruppe_id', 'modul_belegung_id'], 'idx_noten_gruppe_belegung');
            $table->index(['lernender_id', 'kategorie_id', 'semester_id', 'pruefungsdatum'], 'idx_noten_filter');

            // FK auf benutzer (erfasst/aktualisiert)
            $table->index('erfasst_von_benutzer_id', 'fk_noten_erfasst');
            $table->index('aktualisiert_von_benutzer_id', 'fk_noten_aktual');

            $table->foreign('lernender_id', 'fk_noten_lernender')
                ->references('lernender_id')->on('lernende');
            $table->foreign('kategorie_id', 'fk_noten_kategorie')
                ->references('kategorie_id')->on('kategorien');
            $table->foreign('semester_id', 'fk_noten_semester')
                ->references('semester_id')->on('semester');
            $table->foreign('fach_id', 'fk_noten_fach')
                ->references('fach_id')->on('faecher');
            $table->foreign('erfasst_von_benutzer_id', 'fk_noten_erfasst')
                ->references('benutzer_id')->on('benutzer');
            $table->foreign('aktualisiert_von_benutzer_id', 'fk_noten_aktual')
                ->references('benutzer_id')->on('benutzer');
        });

        // Composite-FKs müssen via raw SQL gesetzt werden (Laravel unterstützt nur einfache FKs)
        DB::statement('ALTER TABLE noten ADD CONSTRAINT fk_noten_belegung
            FOREIGN KEY (modul_belegung_id, lernender_id) REFERENCES modul_belegungen (modul_belegung_id, lernender_id)');
        DB::statement('ALTER TABLE noten ADD CONSTRAINT fk_noten_gruppe_belegung
            FOREIGN KEY (gruppe_id, modul_belegung_id) REFERENCES modul_note_gruppen (gruppe_id, modul_belegung_id)');

        // Notenwert muss im Schweizer Notensystem (1.0–6.0) liegen
        DB::statement('ALTER TABLE noten ADD CONSTRAINT chk_noten_note_wert CHECK (note_wert >= 1.0 AND note_wert <= 6.0)');
        // Gewichtung muss zwischen 0 und 100 liegen (wenn gesetzt)
        DB::statement('ALTER TABLE noten ADD CONSTRAINT chk_noten_gewichtung CHECK (gewichtung_prozent IS NULL OR (gewichtung_prozent >= 0 AND gewichtung_prozent <= 100))');
        // Entweder Fach ODER Modul-Belegung muss gesetzt sein – nie beide, nie keines
        DB::statement('ALTER TABLE noten ADD CONSTRAINT chk_noten_fach_xor_modul CHECK (
            (fach_id IS NOT NULL AND modul_belegung_id IS NULL) OR
            (fach_id IS NULL AND modul_belegung_id IS NOT NULL)
        )');
        // Gruppe darf nur gesetzt sein, wenn auch modul_belegung_id gesetzt ist
        DB::statement('ALTER TABLE noten ADD CONSTRAINT chk_noten_gruppe_needs_belegung CHECK (gruppe_id IS NULL OR modul_belegung_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('noten');
    }
};
