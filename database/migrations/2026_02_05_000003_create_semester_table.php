<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester', function (Blueprint $table) {
            $table->increments('semester_id');
            $table->string('bezeichnung', 20)->unique('uk_semester_bezeichnung');
            $table->date('start_datum');
            $table->date('end_datum');
            $table->unsignedSmallInteger('sortierung');

            $table->unique(['start_datum', 'end_datum'], 'uk_semester_range');
            $table->index(['sortierung', 'start_datum'], 'idx_semester_sort');
            $table->index(['start_datum', 'end_datum'], 'idx_semester_range');
        });

        // end_datum muss >= start_datum sein
        DB::statement('ALTER TABLE semester ADD CONSTRAINT chk_semester_datum CHECK (end_datum >= start_datum)');
    }

    public function down(): void
    {
        Schema::dropIfExists('semester');
    }
};
