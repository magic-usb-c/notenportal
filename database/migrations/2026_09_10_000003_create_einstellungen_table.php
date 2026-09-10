<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einstellungen', function (Blueprint $table) {
            $table->string('schluessel', 100)->primary();
            $table->text('wert')->nullable();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einstellungen');
    }
};
