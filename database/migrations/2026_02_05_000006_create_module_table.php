<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module', function (Blueprint $table) {
            $table->increments('modul_id');
            $table->string('modul_nummer', 50)->unique('uk_module_nummer');
            $table->string('titel', 255);
            $table->text('beschreibung')->nullable();
            // Ziel-Summe aller Gewichtungen innerhalb eines Moduls (Default 100%)
            $table->decimal('ziel_gewicht_summe_default', 6, 2)->default(100.00);
            $table->boolean('aktiv')->default(true);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->index('modul_nummer', 'idx_module_nummer');
        });

        DB::statement('ALTER TABLE module ADD CONSTRAINT chk_module_gewicht CHECK (ziel_gewicht_summe_default >= 0.00)');
    }

    public function down(): void
    {
        Schema::dropIfExists('module');
    }
};
