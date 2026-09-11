<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stimmen «Betrifft mich auch» für offene Feedback-Hauptmeldungen und Duplikat-Markierung durch
 * Admins (feedback.duplikat_von). Jeder Teil einzeln mit Guard, weil MariaDB-DDL nicht
 * transaktional ist – ein abgebrochener Lauf lässt sich so gefahrlos wiederholen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_stimmen')) {
            Schema::create('feedback_stimmen', function (Blueprint $table) {
                $table->increments('feedback_stimme_id');
                $table->unsignedInteger('feedback_id');
                $table->unsignedInteger('benutzer_id');
                $table->dateTime('erstellt_am')->useCurrent();

                // Deckt auch die Anzahl Stimmen je Meldung ab (COUNT über den Index).
                $table->unique(['feedback_id', 'benutzer_id'], 'uq_feedback_stimme');
                $table->index('benutzer_id', 'idx_feedback_stimme_benutzer');

                $table->foreign('feedback_id', 'fk_feedback_stimme_feedback')
                    ->references('feedback_id')->on('feedback')->cascadeOnDelete();
                // Benutzer werden nur soft-gelöscht (geloescht_am); die Cascade greift nur bei echtem Löschen.
                $table->foreign('benutzer_id', 'fk_feedback_stimme_benutzer')
                    ->references('benutzer_id')->on('benutzer')->cascadeOnDelete();
            });
        }

        $db = DB::connection()->getDatabaseName();

        if (! Schema::hasColumn('feedback', 'duplikat_von')) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->unsignedInteger('duplikat_von')->nullable()->after('status');
            });
        }

        $hatFk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('TABLE_NAME', 'feedback')
            ->where('CONSTRAINT_NAME', 'fk_feedback_duplikat')
            ->exists();
        if (! $hatFk) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->foreign('duplikat_von', 'fk_feedback_duplikat')
                    ->references('feedback_id')->on('feedback')->nullOnDelete();
            });
        }

        $hatIndex = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'feedback')
            ->where('INDEX_NAME', 'idx_feedback_route_status')
            ->exists();
        if (! $hatIndex) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->index(['route_name', 'status'], 'idx_feedback_route_status');
            });
        }
    }

    /**
     * Einmal zurückgeführte Stimmen bleiben beim Rollback nicht erhalten – das ist akzeptabel.
     * Namen von FK und Index werden über information_schema gesucht statt angenommen.
     */
    public function down(): void
    {
        $db = DB::connection()->getDatabaseName();

        $hatIndex = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'feedback')
            ->where('INDEX_NAME', 'idx_feedback_route_status')
            ->exists();
        if ($hatIndex) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->dropIndex('idx_feedback_route_status');
            });
        }

        if (Schema::hasColumn('feedback', 'duplikat_von')) {
            $fk = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('CONSTRAINT_SCHEMA', $db)
                ->where('TABLE_NAME', 'feedback')
                ->where('COLUMN_NAME', 'duplikat_von')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');
            if ($fk) {
                DB::statement('ALTER TABLE feedback DROP FOREIGN KEY `'.str_replace('`', '', $fk).'`');
            }

            Schema::table('feedback', function (Blueprint $table) {
                $table->dropColumn('duplikat_von');
            });
        }

        Schema::dropIfExists('feedback_stimmen');
    }
};
