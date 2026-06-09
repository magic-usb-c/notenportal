<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faecher', function (Blueprint $table) {
            $table->increments('fach_id');
            // BMS = Berufsmaturitätsschule, ABU = Allgemeinbildender Unterricht
            $table->enum('track_typ', ['BMS', 'ABU']);
            $table->string('name', 200);
            $table->string('kurzname', 50);
            $table->boolean('aktiv')->default(true);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['track_typ', 'name'], 'uk_faecher_typ_name');
            $table->unique(['track_typ', 'kurzname'], 'uk_faecher_typ_kurz');
            $table->index('track_typ', 'idx_faecher_typ');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faecher');
    }
};
