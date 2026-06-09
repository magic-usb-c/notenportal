<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welche Module gehören zu welchem Lehrberuf (Pflicht/Wahlmodul).
     * Basis für die Modul-Auswahl im Notenformular.
     */
    public function up(): void
    {
        Schema::create('lehrberuf_module', function (Blueprint $table) {
            $table->unsignedInteger('lehrberuf_id');
            $table->unsignedInteger('modul_id');
            $table->boolean('pflicht')->default(true);
            $table->unsignedSmallInteger('empfohlenes_lehrsemester_nr')->nullable();
            $table->boolean('aktiv')->default(true);

            $table->primary(['lehrberuf_id', 'modul_id']);
            $table->index('modul_id', 'idx_lbmod_modul');
            $table->index(['lehrberuf_id', 'aktiv'], 'idx_lbmod_lehrberuf');

            $table->foreign('lehrberuf_id', 'fk_lbmod_lehrberuf')
                ->references('lehrberuf_id')->on('lehrberufe');
            $table->foreign('modul_id', 'fk_lbmod_modul')
                ->references('modul_id')->on('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lehrberuf_module');
    }
};
