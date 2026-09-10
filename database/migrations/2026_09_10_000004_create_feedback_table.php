<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback/Ideen/Bugs von Benutzern, erfasst über den schwebenden
     * Feedback-Button im App-Layout. Admin sichtet und bearbeitet den Status.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->increments('feedback_id');
            $table->unsignedInteger('benutzer_id');
            $table->enum('kategorie', ['feedback', 'idee', 'bug']);
            $table->text('text');
            $table->string('route_name', 150)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('viewport', 20)->nullable();
            $table->enum('status', ['offen', 'in_arbeit', 'erledigt'])->default('offen');
            $table->text('admin_notiz')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('erledigt_am')->nullable();

            $table->index('status', 'idx_feedback_status');
            $table->index('benutzer_id', 'idx_feedback_benutzer');

            $table->foreign('benutzer_id', 'fk_feedback_benutzer')
                ->references('benutzer_id')->on('benutzer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
