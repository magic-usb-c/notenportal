<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aktivitätsprotokoll (Audit-Log) für Admins: sicherheitsrelevante Aktionen (Anmeldung,
 * Kontoänderungen, Betrieb, Sicherungen, Noten, Betreuung …), geschrieben über
 * App\Support\Protokoll::schreiben(). Geheimnisse landen nie in «details» (dort gefiltert).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aktivitaeten')) {
            return;
        }

        Schema::create('aktivitaeten', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('benutzer_id')->nullable();
            $table->string('aktion', 60)->index();
            $table->string('ziel_typ', 40)->nullable();
            $table->unsignedBigInteger('ziel_id')->nullable();
            $table->string('ziel_bezeichnung', 150)->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('erstellt_am')->useCurrent()->index();

            $table->foreign('benutzer_id')->references('benutzer_id')->on('benutzer')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitaeten');
    }
};
