<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollen', function (Blueprint $table) {
            $table->increments('rolle_id');
            $table->string('name', 50)->unique('uk_rollen_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollen');
    }
};
