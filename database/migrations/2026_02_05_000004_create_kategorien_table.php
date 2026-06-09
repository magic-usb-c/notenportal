<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategorien', function (Blueprint $table) {
            $table->increments('kategorie_id');
            $table->string('code', 30)->unique('uk_kategorien_code');
            $table->string('name', 50)->unique('uk_kategorien_name');
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->boolean('aktiv')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategorien');
    }
};
