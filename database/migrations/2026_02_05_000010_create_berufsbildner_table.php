<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berufsbildner', function (Blueprint $table) {
            $table->increments('berufsbildner_id');
            $table->unsignedInteger('benutzer_id')->unique('uk_berufsbildner_benutzer');
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('geloescht_am')->nullable(); // Soft Delete

            $table->foreign('benutzer_id', 'fk_berufsbildner_benutzer')
                ->references('benutzer_id')->on('benutzer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berufsbildner');
    }
};
