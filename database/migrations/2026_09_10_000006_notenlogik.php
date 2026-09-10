<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notenlogik (docs/notenlogik.md):
 * - Kategorie einer Note folgt aus Fach bzw. Modul (faecher.kategorie_id, lehrberuf_module.kategorie_id)
 * - Rechenregeln je Kategorie (Rundung, Gewicht im Gesamtschnitt, Promotion)
 * - geplante Prüfungen und Ziele
 * - Modul-Notengruppen und Bewertungsregeln entfallen (nie erfassbar, ersetzt)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategorien', function (Blueprint $table) {
            $table->decimal('rundung_element', 3, 2)->default(0.50)->after('aktiv');
            $table->decimal('rundung_schnitt', 3, 2)->default(0.10)->after('rundung_element');
            $table->decimal('gewicht_gesamt', 5, 2)->default(1.00)->after('rundung_schnitt');
            $table->decimal('promotion_min_schnitt', 3, 2)->nullable()->after('gewicht_gesamt');
            $table->unsignedTinyInteger('promotion_max_ungenuegend')->nullable()->after('promotion_min_schnitt');
            $table->decimal('promotion_max_minuspunkte', 4, 2)->nullable()->after('promotion_max_ungenuegend');
        });
        DB::statement('ALTER TABLE kategorien ADD CONSTRAINT chk_kat_rundung CHECK (rundung_element >= 0 AND rundung_element <= 1 AND rundung_schnitt >= 0 AND rundung_schnitt <= 1)');
        DB::statement('ALTER TABLE kategorien ADD CONSTRAINT chk_kat_gewicht CHECK (gewicht_gesamt >= 0)');
        DB::statement('ALTER TABLE kategorien ADD CONSTRAINT chk_kat_promotion CHECK (promotion_min_schnitt IS NULL OR (promotion_min_schnitt >= 1 AND promotion_min_schnitt <= 6))');
        DB::table('kategorien')->where('code', 'BMS')->update([
            'promotion_min_schnitt' => 4.0, 'promotion_max_ungenuegend' => 2, 'promotion_max_minuspunkte' => 2.0,
        ]);

        // Fach → Kategorie; Track nur noch Voraussetzung
        Schema::table('faecher', function (Blueprint $table) {
            $table->unsignedInteger('kategorie_id')->nullable()->after('fach_id');
            $table->foreign('kategorie_id', 'fk_faecher_kategorie')->references('kategorie_id')->on('kategorien');
        });
        DB::statement('ALTER TABLE faecher MODIFY track_typ ENUM(\'BMS\',\'ABU\') NULL');
        DB::statement('UPDATE faecher f JOIN kategorien k ON k.code = f.track_typ SET f.kategorie_id = k.kategorie_id WHERE f.kategorie_id IS NULL');
        $fachunterricht = DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id');
        if ($fachunterricht) {
            DB::table('faecher')->whereNull('kategorie_id')->update(['kategorie_id' => $fachunterricht]);
        }

        // Modul → Lernort je Beruf (Fachunterricht oder ÜK)
        Schema::table('lehrberuf_module', function (Blueprint $table) {
            $table->unsignedInteger('kategorie_id')->nullable()->after('modul_id');
            $table->foreign('kategorie_id', 'fk_lbmod_kategorie')->references('kategorie_id')->on('kategorien');
        });
        $uek = DB::table('kategorien')->where('code', 'UEK')->value('kategorie_id');
        if ($fachunterricht) {
            DB::table('lehrberuf_module')->update(['kategorie_id' => $fachunterricht]);
        }
        if ($uek) {
            // Module, deren bisherige Noten überwiegend als ÜK erfasst wurden, werden ÜK-Module
            DB::statement('UPDATE lehrberuf_module lbm SET lbm.kategorie_id = ? WHERE (
                SELECT COALESCE(SUM(n.kategorie_id = ?) > SUM(n.kategorie_id <> ?), 0)
                FROM noten n
                JOIN modul_belegungen mb ON mb.modul_belegung_id = n.modul_belegung_id
                JOIN lernende l ON l.lernender_id = n.lernender_id
                WHERE mb.modul_id = lbm.modul_id AND l.lehrberuf_id = lbm.lehrberuf_id AND n.geloescht_am IS NULL
            ) = 1', [$uek, $uek, $uek]);
        }

        // Bestehende Noten auf die abgeleitete Kategorie bringen
        DB::statement('UPDATE noten n JOIN faecher f ON f.fach_id = n.fach_id SET n.kategorie_id = f.kategorie_id WHERE f.kategorie_id IS NOT NULL');
        DB::statement('UPDATE noten n
            JOIN modul_belegungen mb ON mb.modul_belegung_id = n.modul_belegung_id
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN lehrberuf_module lbm ON lbm.modul_id = mb.modul_id AND lbm.lehrberuf_id = l.lehrberuf_id
            SET n.kategorie_id = lbm.kategorie_id WHERE lbm.kategorie_id IS NOT NULL');

        // Notengruppen entfernen (ältere Installationen haben nicht alle Constraints/Indizes)
        $checks = DB::table('information_schema.CHECK_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'noten')
            ->where('CHECK_CLAUSE', 'like', '%gruppe_id%')
            ->pluck('CONSTRAINT_NAME');
        foreach ($checks as $name) {
            DB::statement('ALTER TABLE noten DROP CONSTRAINT `'.str_replace('`', '', $name).'`');
        }
        DB::statement('ALTER TABLE noten DROP FOREIGN KEY IF EXISTS fk_noten_gruppe_belegung');
        DB::statement('ALTER TABLE noten DROP INDEX IF EXISTS idx_noten_gruppe_belegung');
        DB::statement('ALTER TABLE noten DROP INDEX IF EXISTS idx_noten_gruppe');
        Schema::table('noten', function (Blueprint $table) {
            $table->dropColumn('gruppe_id');
        });
        Schema::dropIfExists('modul_note_gruppen');
        Schema::dropIfExists('bewertungsregeln');

        Schema::create('pruefungen', function (Blueprint $table) {
            $table->increments('pruefung_id');
            $table->unsignedInteger('lernender_id');
            $table->unsignedInteger('fach_id')->nullable();
            $table->unsignedInteger('modul_id')->nullable();
            $table->string('titel', 150)->nullable();
            $table->date('datum');
            $table->decimal('gewichtung_prozent', 6, 2)->default(100.00);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->index(['lernender_id', 'datum'], 'idx_pruef_lernender_datum');
            $table->foreign('lernender_id', 'fk_pruef_lernender')->references('lernender_id')->on('lernende');
            $table->foreign('fach_id', 'fk_pruef_fach')->references('fach_id')->on('faecher');
            $table->foreign('modul_id', 'fk_pruef_modul')->references('modul_id')->on('module');
        });
        DB::statement('ALTER TABLE pruefungen ADD CONSTRAINT chk_pruef_fach_xor_modul CHECK ((fach_id IS NOT NULL AND modul_id IS NULL) OR (fach_id IS NULL AND modul_id IS NOT NULL))');
        DB::statement('ALTER TABLE pruefungen ADD CONSTRAINT chk_pruef_gewichtung CHECK (gewichtung_prozent >= 0 AND gewichtung_prozent <= 100)');

        Schema::create('ziele', function (Blueprint $table) {
            $table->increments('ziel_id');
            $table->unsignedInteger('lernender_id');
            $table->enum('ebene', ['gesamt', 'kategorie', 'fach', 'modul']);
            $table->unsignedInteger('kategorie_id')->nullable();
            $table->unsignedInteger('fach_id')->nullable();
            $table->unsignedInteger('modul_id')->nullable();
            $table->decimal('zielwert', 3, 2);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();
            $table->index('lernender_id', 'idx_ziele_lernender');
            $table->foreign('lernender_id', 'fk_ziele_lernender')->references('lernender_id')->on('lernende');
            $table->foreign('kategorie_id', 'fk_ziele_kategorie')->references('kategorie_id')->on('kategorien');
            $table->foreign('fach_id', 'fk_ziele_fach')->references('fach_id')->on('faecher');
            $table->foreign('modul_id', 'fk_ziele_modul')->references('modul_id')->on('module');
        });
        DB::statement('ALTER TABLE ziele ADD CONSTRAINT chk_ziele_wert CHECK (zielwert >= 1 AND zielwert <= 6)');
        DB::statement("ALTER TABLE ziele ADD CONSTRAINT chk_ziele_scope CHECK (
            ebene = 'gesamt'    AND kategorie_id IS NULL AND fach_id IS NULL AND modul_id IS NULL OR
            ebene = 'kategorie' AND kategorie_id IS NOT NULL AND fach_id IS NULL AND modul_id IS NULL OR
            ebene = 'fach'      AND kategorie_id IS NULL AND fach_id IS NOT NULL AND modul_id IS NULL OR
            ebene = 'modul'     AND kategorie_id IS NULL AND fach_id IS NULL AND modul_id IS NOT NULL
        )");

        foreach (['note_gut' => '5.0', 'note_genuegend' => '4.0', 'note_kritisch' => '3.5', 'rundung_gesamt' => '0.1'] as $schluessel => $wert) {
            DB::table('einstellungen')->insertOrIgnore(['schluessel' => $schluessel, 'wert' => $wert]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ziele');
        Schema::dropIfExists('pruefungen');

        Schema::table('noten', function (Blueprint $table) {
            $table->unsignedInteger('gruppe_id')->nullable()->after('modul_belegung_id');
        });

        Schema::table('lehrberuf_module', function (Blueprint $table) {
            $table->dropForeign('fk_lbmod_kategorie');
            $table->dropColumn('kategorie_id');
        });

        DB::statement("UPDATE faecher f JOIN kategorien k ON k.kategorie_id = f.kategorie_id SET f.track_typ = IF(k.code = 'ABU', 'ABU', 'BMS') WHERE f.track_typ IS NULL");
        DB::statement('ALTER TABLE faecher MODIFY track_typ ENUM(\'BMS\',\'ABU\') NOT NULL');
        Schema::table('faecher', function (Blueprint $table) {
            $table->dropForeign('fk_faecher_kategorie');
            $table->dropColumn('kategorie_id');
        });

        foreach (['chk_kat_rundung', 'chk_kat_gewicht', 'chk_kat_promotion'] as $constraint) {
            DB::statement("ALTER TABLE kategorien DROP CONSTRAINT {$constraint}");
        }
        Schema::table('kategorien', function (Blueprint $table) {
            $table->dropColumn(['rundung_element', 'rundung_schnitt', 'gewicht_gesamt', 'promotion_min_schnitt', 'promotion_max_ungenuegend', 'promotion_max_minuspunkte']);
        });

        DB::table('einstellungen')->whereIn('schluessel', ['note_gut', 'note_genuegend', 'note_kritisch', 'rundung_gesamt'])->delete();
    }
};
