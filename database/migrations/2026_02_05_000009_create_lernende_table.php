<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lernende', function (Blueprint $table) {
            $table->increments('lernender_id');
            $table->unsignedInteger('benutzer_id')->unique('uk_lernende_benutzer');
            $table->unsignedInteger('lehrberuf_id');
            $table->date('lehrbeginn');
            $table->date('lehrende')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('geloescht_am')->nullable(); // Soft Delete

            $table->index('lehrberuf_id', 'idx_lernende_lehrberuf');
            $table->index(['lehrbeginn', 'lehrende'], 'idx_lernende_lehrzeit');

            $table->foreign('benutzer_id', 'fk_lernende_benutzer')
                ->references('benutzer_id')->on('benutzer');
            $table->foreign('lehrberuf_id', 'fk_lernende_lehrberuf')
                ->references('lehrberuf_id')->on('lehrberufe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lernende');
    }
};
