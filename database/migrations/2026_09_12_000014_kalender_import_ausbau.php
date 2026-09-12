<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rückmeldung #15 Phase 1 (ICS-Import ausbauen): lokale Sperren pro Feld (`lokal_gesperrt`,
 * Objekt statt Liste, damit die Oberfläche den zuletzt gemeldeten Quellwert zeigen kann), Zuordnung
 * jeder importierten Prüfung zu ihrem Feed (`calendar_feed_id` – ohne diese Spalte würde der
 * Abgleich eines zweiten Kalenders die Prüfungen des ersten aufräumen) sowie ETag/Last-Modified
 * je Feed, damit ein unveränderter Kalender nichts kostet (304 statt Neuparsen).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pruefungen', 'lokal_gesperrt')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                $table->json('lokal_gesperrt')->nullable()->after('abgesagt_am');
            });
        }

        if (! Schema::hasColumn('pruefungen', 'calendar_feed_id')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                $table->unsignedBigInteger('calendar_feed_id')->nullable()->after('lokal_gesperrt');
                $table->foreign('calendar_feed_id', 'fk_pruef_calendar_feed')->references('id')->on('calendar_feeds')->nullOnDelete();
            });

            // Bestandsdaten: heute hat jeder Lernende höchstens einen Feed, also bekommen alle
            // ical-Prüfungen den Feed ihres Lernenden. Liefern künftig zwei Feeds dieselbe UID,
            // gewinnt der zuletzt abgleichende Feed (uq_pruef_extern) – siehe docs/audit-backlog.md.
            DB::statement(<<<'SQL'
                UPDATE pruefungen p
                JOIN calendar_feeds cf ON cf.lernender_id = p.lernender_id
                SET p.calendar_feed_id = cf.id
                WHERE p.quelle = 'ical' AND p.calendar_feed_id IS NULL
            SQL);
        }

        if (! Schema::hasColumn('calendar_feeds', 'etag')) {
            Schema::table('calendar_feeds', function (Blueprint $table) {
                $table->string('etag', 255)->nullable()->after('url');
            });
        }

        if (! Schema::hasColumn('calendar_feeds', 'last_modified')) {
            Schema::table('calendar_feeds', function (Blueprint $table) {
                // HTTP-Datum als Zeichenkette: wird unverändert als If-Modified-Since zurückgeschickt,
                // keine Umwandlung nötig und kein Risiko durch abweichende Zeitzonen-Interpretation.
                $table->string('last_modified', 64)->nullable()->after('etag');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('calendar_feeds', 'last_modified')) {
            Schema::table('calendar_feeds', function (Blueprint $table) {
                $table->dropColumn('last_modified');
            });
        }

        if (Schema::hasColumn('calendar_feeds', 'etag')) {
            Schema::table('calendar_feeds', function (Blueprint $table) {
                $table->dropColumn('etag');
            });
        }

        if (Schema::hasColumn('pruefungen', 'calendar_feed_id')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                $table->dropForeign('fk_pruef_calendar_feed');
                $table->dropColumn('calendar_feed_id');
            });
        }

        if (Schema::hasColumn('pruefungen', 'lokal_gesperrt')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                $table->dropColumn('lokal_gesperrt');
            });
        }
    }
};
