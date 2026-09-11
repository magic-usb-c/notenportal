<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dritte Übernahme-Option je Kalender-Abo: Prüfungen separat von Terminen/Lektionen ein- und ausschaltbar,
 * wie vom Lernenden in der Agenda gewünscht («Optionen: Prüfungen / Termine / Lektionen übernehmen»).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_feeds', 'import_exams')) {
            Schema::table('calendar_feeds', function (Blueprint $table) {
                $table->boolean('import_exams')->default(true)->after('label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('calendar_feeds', 'import_exams')) {
            Schema::table('calendar_feeds', function (Blueprint $table) {
                $table->dropColumn('import_exams');
            });
        }
    }
};
