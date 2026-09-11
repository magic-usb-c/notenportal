<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfungsplanung als Agenda: Prüfungsdetails (Uhrzeit, Dauer, Art, Hilfsmittel, Stoff, Notizen),
 * Verknüpfung mit der Note statt Löschen, Herkunft iCal; Anhänge über dokumente; abonnierte
 * Kalender (calendar_feeds) mit ihren Terminen (calendar_events); Export-Token je Benutzer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pruefungen', function (Blueprint $table) {
            if (! Schema::hasColumn('pruefungen', 'uhrzeit')) {
                $table->time('uhrzeit')->nullable()->after('datum');
                $table->unsignedSmallInteger('dauer_minuten')->nullable()->after('uhrzeit');
                $table->string('pruefungsart', 150)->nullable()->after('titel');
                $table->string('hilfsmittel', 255)->nullable()->after('pruefungsart');
                $table->text('stoff')->nullable()->after('hilfsmittel');
                $table->text('notizen')->nullable()->after('stoff');
                $table->string('raum', 60)->nullable()->after('notizen');
                $table->string('lehrperson', 60)->nullable()->after('raum');
                $table->unsignedInteger('note_id')->nullable()->after('gewichtung_prozent');
                $table->string('quelle', 10)->default('manuell')->after('note_id');
                $table->string('extern_uid', 255)->nullable()->after('quelle');
                $table->dateTime('abgesagt_am')->nullable()->after('extern_uid');
                $table->unique(['lernender_id', 'extern_uid'], 'uq_pruef_extern');
                $table->unique('note_id', 'uq_pruef_note');
                $table->foreign('note_id', 'fk_pruef_note')->references('note_id')->on('noten')->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('dokumente', 'pruefung_id')) {
            Schema::table('dokumente', function (Blueprint $table) {
                $table->unsignedInteger('pruefung_id')->nullable()->after('semester_id');
                $table->foreign('pruefung_id', 'fk_dokumente_pruefung')->references('pruefung_id')->on('pruefungen')->nullOnDelete();
            });
            DB::statement("ALTER TABLE dokumente MODIFY art ENUM('zeugnis','notenliste','pruefung','sonstiges') NOT NULL DEFAULT 'sonstiges'");
        }

        if (! Schema::hasColumn('benutzer', 'kalender_token')) {
            Schema::table('benutzer', function (Blueprint $table) {
                $table->string('kalender_token', 64)->nullable()->unique('uq_benutzer_kalender_token');
            });
        }

        if (! Schema::hasTable('calendar_feeds')) {
            Schema::create('calendar_feeds', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('lernender_id');
                $table->string('label', 80)->nullable();
                $table->text('url');
                $table->boolean('import_lessons')->default(true);
                $table->boolean('import_appointments')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_status', 16)->nullable();
                $table->string('last_error', 500)->nullable();
                $table->unsignedInteger('events_count')->default(0);
                $table->timestamps();
                $table->foreign('lernender_id')->references('lernender_id')->on('lernende')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('lernender_id');
                $table->foreignId('calendar_feed_id')->constrained('calendar_feeds')->cascadeOnDelete();
                $table->string('uid', 255);
                $table->string('kind', 16);
                $table->string('summary', 255);
                $table->text('description')->nullable();
                $table->string('location', 120)->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->boolean('all_day')->default(false);
                $table->string('course_code', 20)->nullable();
                $table->unsignedInteger('pruefung_id')->nullable();
                $table->timestamps();
                $table->unique(['calendar_feed_id', 'uid', 'starts_at'], 'uq_calendar_event');
                $table->index(['lernender_id', 'starts_at']);
                $table->foreign('lernender_id')->references('lernender_id')->on('lernende')->cascadeOnDelete();
                $table->foreign('pruefung_id')->references('pruefung_id')->on('pruefungen')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_feeds');
        if (Schema::hasColumn('benutzer', 'kalender_token')) {
            Schema::table('benutzer', function (Blueprint $table) {
                $table->dropUnique('uq_benutzer_kalender_token');
                $table->dropColumn('kalender_token');
            });
        }
        if (Schema::hasColumn('dokumente', 'pruefung_id')) {
            DB::statement("UPDATE dokumente SET art = 'sonstiges' WHERE art = 'pruefung'");
            DB::statement("ALTER TABLE dokumente MODIFY art ENUM('zeugnis','notenliste','sonstiges') NOT NULL DEFAULT 'sonstiges'");
            Schema::table('dokumente', function (Blueprint $table) {
                $table->dropForeign('fk_dokumente_pruefung');
                $table->dropColumn('pruefung_id');
            });
        }
        if (Schema::hasColumn('pruefungen', 'uhrzeit')) {
            Schema::table('pruefungen', function (Blueprint $table) {
                $table->dropForeign('fk_pruef_note');
                $table->dropUnique('uq_pruef_note');
                $table->dropUnique('uq_pruef_extern');
                $table->dropColumn(['uhrzeit', 'dauer_minuten', 'pruefungsart', 'hilfsmittel', 'stoff', 'notizen', 'raum', 'lehrperson', 'note_id', 'quelle', 'extern_uid', 'abgesagt_am']);
            });
        }
    }
};
