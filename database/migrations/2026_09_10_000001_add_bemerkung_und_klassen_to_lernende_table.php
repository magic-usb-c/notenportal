<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lernende', function (Blueprint $table) {
            $table->text('bemerkung')->nullable()->after('lehrende');
            $table->string('klasse_schule', 30)->nullable()->after('bemerkung');
            $table->string('klasse_bms', 30)->nullable()->after('klasse_schule');
        });
    }

    public function down(): void
    {
        Schema::table('lernende', function (Blueprint $table) {
            $table->dropColumn(['bemerkung', 'klasse_schule', 'klasse_bms']);
        });
    }
};
