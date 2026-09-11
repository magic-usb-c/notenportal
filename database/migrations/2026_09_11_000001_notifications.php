<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Mail und Benachrichtigungen: Queue-Tabellen (Datenbank-Queue), Passwort-Reset-Tokens,
 * globale Regeln je Anlass, persönliche Wahl je Benutzer, Versandprotokoll, Tageszusammenfassung
 * und Merker gegen Doppelversand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        Schema::create('notification_policies', function (Blueprint $table) {
            $table->string('type', 64)->primary();
            $table->boolean('enabled')->default(true);
            $table->boolean('mandatory')->default(false);
            $table->string('frequency', 16)->default('immediate');
            $table->json('params')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('type', 64);
            $table->string('frequency', 16);
            $table->timestamps();
            $table->unique(['user_id', 'type']);
            $table->foreign('user_id')->references('benutzer_id')->on('benutzer')->cascadeOnDelete();
        });

        Schema::create('mail_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('type', 64);
            $table->string('recipient');
            $table->string('redirected_to')->nullable();
            $table->string('subject');
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->foreign('user_id')->references('benutzer_id')->on('benutzer')->nullOnDelete();
        });

        Schema::create('notification_digest_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('type', 64);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->index(['sent_at', 'user_id']);
            $table->foreign('user_id')->references('benutzer_id')->on('benutzer')->cascadeOnDelete();
        });

        Schema::create('notification_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('type', 64);
            $table->string('subject_key', 191);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'type', 'subject_key']);
            $table->foreign('user_id')->references('benutzer_id')->on('benutzer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_marks');
        Schema::dropIfExists('notification_digest_items');
        Schema::dropIfExists('mail_log');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_policies');
        // jobs, job_batches, failed_jobs, password_reset_tokens bleiben: Laravel-Standard, von anderen Teilen genutzt
    }
};
