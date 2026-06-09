<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ersetzt Laravels Standard-users-Tabelle.
     * Verwendet deutschsprachige Spaltennamen gemäss Projektkonvention.
     * passwort_hash wird von Laravel via getAuthPassword()/getAuthPasswordName() gemappt.
     */
    public function up(): void
    {
        Schema::create('benutzer', function (Blueprint $table) {
            $table->increments('benutzer_id');
            $table->string('benutzername', 50)->unique('uk_benutzer_benutzername');
            $table->string('email', 255)->unique('uk_benutzer_email');
            $table->string('vorname', 100);
            $table->string('nachname', 100);
            $table->string('passwort_hash', 100);
            $table->boolean('aktiv')->default(true);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('geloescht_am')->nullable(); // Soft Delete

            $table->index(['nachname', 'vorname'], 'idx_benutzer_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benutzer');
    }
};
