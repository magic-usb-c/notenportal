<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benutzer_rollen', function (Blueprint $table) {
            $table->unsignedInteger('benutzer_id');
            $table->unsignedInteger('rolle_id');

            $table->primary(['benutzer_id', 'rolle_id']);
            $table->index('rolle_id', 'idx_benutzerrollen_rolle');

            $table->foreign('benutzer_id', 'fk_benutzer_rollen_benutzer')
                ->references('benutzer_id')->on('benutzer');
            $table->foreign('rolle_id', 'fk_benutzer_rollen_rolle')
                ->references('rolle_id')->on('rollen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benutzer_rollen');
    }
};
