<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->boolean('passwort_wechsel_noetig')->default(false)->after('passwort_hash');
            $table->enum('darstellung', ['system', 'hell', 'dunkel'])->default('system')->after('aktiv');
        });
    }

    public function down(): void
    {
        Schema::table('benutzer', function (Blueprint $table) {
            $table->dropColumn(['passwort_wechsel_noetig', 'darstellung']);
        });
    }
};
