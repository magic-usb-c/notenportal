<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Formular erlaubt Viertelnoten (4.25), decimal(3,1) hat sie auf 4.3 gerundet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noten', function (Blueprint $table) {
            $table->decimal('note_wert', 4, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('noten', function (Blueprint $table) {
            $table->decimal('note_wert', 3, 1)->change();
        });
    }
};
