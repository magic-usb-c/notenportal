<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lehrberufe', function (Blueprint $table) {
            $table->increments('lehrberuf_id');
            $table->string('kuerzel', 10)->unique('uk_lehrberufe_kuerzel');
            $table->string('name', 200)->unique('uk_lehrberufe_name');
            $table->boolean('aktiv')->default(true);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lehrberufe');
    }
};
