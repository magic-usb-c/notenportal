<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulkatalog (Rückmeldung #17): offizielle Stammdaten je Modul aus dem Modulbaukasten von
 * ICT-Berufsbildung Schweiz – Version, Kompetenzfeld, Kompetenz, Objekt, Handlungsziele und die
 * Leistungsbeurteilungsvorgabe (LBV). Die Version ist der Schlüssel zum Tiefenlink auf die
 * Modulseite; ohne Version verlinkt das Portal bewusst nicht (geratene Adressen wären falsch).
 *
 * Der Katalog selbst liegt nie im Repo: Inhalte des Modulbaukastens gehören ICT-Berufsbildung
 * Schweiz. Gefüllt wird ausschliesslich über `notenportal:modulkatalog` auf der eigenen Instanz.
 * Begründung und Ablauf: docs/modulkatalog.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module', function (Blueprint $table) {
            if (! Schema::hasColumn('module', 'version')) {
                // Katalogversion als Text: es gibt «1», «3», aber auch Module ganz ohne Version.
                $table->string('version', 10)->nullable()->after('titel');
            }
            if (! Schema::hasColumn('module', 'kompetenzfeld')) {
                $table->string('kompetenzfeld', 120)->nullable()->after('version');
            }
            if (! Schema::hasColumn('module', 'kompetenz')) {
                $table->text('kompetenz')->nullable()->after('kompetenzfeld');
            }
            if (! Schema::hasColumn('module', 'objekt')) {
                $table->text('objekt')->nullable()->after('kompetenz');
            }
            if (! Schema::hasColumn('module', 'publiziert_am')) {
                $table->date('publiziert_am')->nullable()->after('objekt');
            }
            if (! Schema::hasColumn('module', 'auslaufend')) {
                // «EOL» im Katalog: Modul läuft aus, bleibt aber für laufende Lehren gültig.
                $table->boolean('auslaufend')->default(false)->after('publiziert_am');
            }
            if (! Schema::hasColumn('module', 'quelle')) {
                $table->string('quelle', 30)->nullable()->after('auslaufend');
            }
            if (! Schema::hasColumn('module', 'quelle_stand')) {
                $table->dateTime('quelle_stand')->nullable()->after('quelle');
            }
        });

        Schema::table('lehrberufe', function (Blueprint $table) {
            if (! Schema::hasColumn('lehrberufe', 'berufsnummer')) {
                // Berufsnummer des SBFI-Berufsverzeichnisses (z. B. 88611, 69201).
                $table->string('berufsnummer', 10)->nullable()->after('name');
            }
            if (! Schema::hasColumn('lehrberufe', 'bildungsstufe')) {
                $table->string('bildungsstufe', 10)->nullable()->after('berufsnummer');
            }
            if (! Schema::hasColumn('lehrberufe', 'bivo_jahr')) {
                $table->smallInteger('bivo_jahr')->unsigned()->nullable()->after('bildungsstufe');
            }
            if (! Schema::hasColumn('lehrberufe', 'quelle_kennung')) {
                // Kennung des Abschlusses in der Quelle, damit ein zweiter Import zuordnen kann.
                $table->string('quelle_kennung', 60)->nullable()->after('bivo_jahr');
            }
        });

        Schema::table('lehrberuf_module', function (Blueprint $table) {
            if (! Schema::hasColumn('lehrberuf_module', 'pflichtgrad')) {
                // «pfl», «wpfl», «wm» aus dem Katalog; `pflicht` bleibt der ausgewertete Schalter.
                $table->string('pflichtgrad', 10)->nullable()->after('pflicht');
            }
        });

        if (! Schema::hasTable('modul_handlungsziele')) {
            Schema::create('modul_handlungsziele', function (Blueprint $table) {
                $table->increments('handlungsziel_id');
                $table->unsignedInteger('modul_id');
                $table->string('nummer', 10);
                $table->text('text');
                $table->unsignedSmallInteger('sortierung')->default(0);
                $table->unique(['modul_id', 'nummer'], 'uq_modul_hz');
                $table->foreign('modul_id', 'fk_modul_hz_modul')->references('modul_id')->on('module')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('modul_lbv_elemente')) {
            Schema::create('modul_lbv_elemente', function (Blueprint $table) {
                $table->increments('lbv_element_id');
                $table->unsignedInteger('modul_id');
                $table->string('bezeichnung', 150);
                // Gewichtung und Richtzeit stehen im Katalog als Prozent bzw. Lektionen-Äquivalent.
                $table->decimal('gewichtung_prozent', 5, 2)->nullable();
                $table->decimal('richtzeit', 5, 2)->nullable();
                $table->string('pruefungsform', 80)->nullable();
                $table->string('sozialform', 80)->nullable();
                $table->text('beschreibung')->nullable();
                $table->unsignedSmallInteger('sortierung')->default(0);
                $table->index('modul_id', 'ix_modul_lbv_modul');
                $table->foreign('modul_id', 'fk_modul_lbv_modul')->references('modul_id')->on('module')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modul_lbv_elemente');
        Schema::dropIfExists('modul_handlungsziele');

        Schema::table('lehrberuf_module', function (Blueprint $table) {
            if (Schema::hasColumn('lehrberuf_module', 'pflichtgrad')) {
                $table->dropColumn('pflichtgrad');
            }
        });

        Schema::table('lehrberufe', function (Blueprint $table) {
            foreach (['berufsnummer', 'bildungsstufe', 'bivo_jahr', 'quelle_kennung'] as $spalte) {
                if (Schema::hasColumn('lehrberufe', $spalte)) {
                    $table->dropColumn($spalte);
                }
            }
        });

        Schema::table('module', function (Blueprint $table) {
            foreach (['version', 'kompetenzfeld', 'kompetenz', 'objekt', 'publiziert_am', 'auslaufend', 'quelle', 'quelle_stand'] as $spalte) {
                if (Schema::hasColumn('module', $spalte)) {
                    $table->dropColumn($spalte);
                }
            }
        });
    }
};
