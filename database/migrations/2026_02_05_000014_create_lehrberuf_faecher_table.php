<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welche Fächer (BMS/ABU) sind für welchen Lehrberuf relevant.
     */
    public function up(): void
    {
        Schema::create('lehrberuf_faecher', function (Blueprint $table) {
            $table->unsignedInteger('lehrberuf_id');
            $table->unsignedInteger('fach_id');
            $table->boolean('aktiv')->default(true);

            $table->primary(['lehrberuf_id', 'fach_id']);
            $table->index('fach_id', 'idx_lbfach_fach');
            $table->index(['lehrberuf_id', 'aktiv'], 'idx_lbfach_lehrberuf');

            $table->foreign('lehrberuf_id', 'fk_lbfach_lehrberuf')
                ->references('lehrberuf_id')->on('lehrberufe');
            $table->foreign('fach_id', 'fk_lbfach_fach')
                ->references('fach_id')->on('faecher');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lehrberuf_faecher');
    }
};
