<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Noten-Gruppen innerhalb einer Modulbelegung (z.B. "Theorie", "Praxis").
     * Ermöglicht separate Gewichtung von Noten-Blöcken.
     * Der Composite-Unique (gruppe_id, modul_belegung_id) dient als zusammengesetzter FK
     * in noten, um Konsistenz zwischen Gruppe und Belegung zu erzwingen.
     */
    public function up(): void
    {
        Schema::create('modul_note_gruppen', function (Blueprint $table) {
            $table->increments('gruppe_id');
            $table->unsignedInteger('modul_belegung_id');
            $table->string('bezeichnung', 100);
            $table->decimal('ziel_gewicht_summe', 6, 2)->default(100.00);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['modul_belegung_id', 'bezeichnung'], 'uk_mng_unique');
            $table->unique(['gruppe_id', 'modul_belegung_id'], 'uk_mng_id_belegung');
            $table->index('modul_belegung_id', 'idx_mng_belegung');

            $table->foreign('modul_belegung_id', 'fk_mng_belegung')
                ->references('modul_belegung_id')->on('modul_belegungen');
        });

        DB::statement('ALTER TABLE modul_note_gruppen ADD CONSTRAINT chk_mng_gewicht CHECK (ziel_gewicht_summe >= 0.00)');
    }

    public function down(): void
    {
        Schema::dropIfExists('modul_note_gruppen');
    }
};
