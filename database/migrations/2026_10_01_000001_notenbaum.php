<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notenbaum (docs/auftrag/NOTENBAUM.md, docs/notenlogik.md):
 * - Bewertungsskala je Fach: Note 1–6 oder Stufe A/B/C/d (Sport); Stufen werden erfasst, nie gerechnet
 * - «zählt nicht» je Fach: erfasst und angezeigt, aber in keinem Schnitt und keiner Promotion (IDAF, Sport)
 * - gewichteter Baum je Lehrberuf (QV) oder Bildungsgang (BM) mit Rundung und Bestehensregeln je Knoten
 * - von Hand erfasste Positionen je Lernender (IPA, Schlussarbeit, Abschlussprüfungen)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faecher', function (Blueprint $table) {
            $table->string('skala', 10)->default('note')->after('kurzname');
            $table->boolean('zaehlt')->default(true)->after('skala');
        });
        DB::statement("ALTER TABLE faecher ADD CONSTRAINT chk_faecher_skala CHECK (skala IN ('note', 'stufe'))");

        // Stufen (A/B/C, d = dispensiert) statt Zahl: genau eines von beiden ist gesetzt. «IS NOT NULL» ist
        // nötig, weil «NULL IN (…)» NULL ergibt und ein CHECK mit NULL-Ergebnis als erfüllt gilt.
        DB::statement('ALTER TABLE noten DROP CONSTRAINT IF EXISTS chk_noten_note_wert');
        Schema::table('noten', function (Blueprint $table) {
            $table->decimal('note_wert', 4, 2)->nullable()->change();
            $table->string('note_stufe', 2)->nullable()->after('note_wert');
        });
        DB::statement("ALTER TABLE noten ADD CONSTRAINT chk_noten_note_wert CHECK (
            (note_wert IS NOT NULL AND note_stufe IS NULL AND note_wert >= 1.0 AND note_wert <= 6.0) OR
            (note_wert IS NULL AND note_stufe IS NOT NULL AND note_stufe IN ('A', 'B', 'C', 'd'))
        )");

        Schema::create('notenbaeume', function (Blueprint $table) {
            $table->increments('baum_id');
            $table->string('name', 150);
            $table->string('bezug', 20)->default('lehrberuf');
            $table->unsignedInteger('lehrberuf_id')->nullable();
            $table->string('track_typ', 20)->nullable();
            $table->string('vorlage', 80)->nullable();
            $table->string('beschreibung', 500)->nullable();
            $table->boolean('aktiv')->default(true);
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->index(['lehrberuf_id', 'aktiv'], 'idx_baum_lehrberuf');
            $table->foreign('lehrberuf_id', 'fk_baum_lehrberuf')->references('lehrberuf_id')->on('lehrberufe');
        });
        DB::statement("ALTER TABLE notenbaeume ADD CONSTRAINT chk_baum_bezug CHECK (
            (bezug = 'lehrberuf' AND lehrberuf_id IS NOT NULL) OR (bezug = 'bildungsgang' AND track_typ IS NOT NULL)
        )");

        Schema::create('notenbaum_knoten', function (Blueprint $table) {
            $table->increments('knoten_id');
            $table->unsignedInteger('baum_id');
            $table->unsignedInteger('eltern_id')->nullable();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('typ', 20);
            $table->decimal('gewicht', 7, 3)->default(1);
            $table->decimal('rundung', 3, 2)->nullable();
            $table->decimal('fallnote', 3, 2)->nullable();
            $table->unsignedTinyInteger('max_ungenuegend')->nullable();
            $table->decimal('max_minuspunkte', 4, 2)->nullable();
            $table->boolean('zaehlt')->default(true);
            $table->unsignedInteger('kategorie_id')->nullable();
            $table->string('elementtyp', 10)->default('alle');
            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->unique(['baum_id', 'code'], 'uk_knoten_code');
            $table->index(['baum_id', 'eltern_id', 'sortierung'], 'idx_knoten_eltern');
            $table->foreign('baum_id', 'fk_knoten_baum')->references('baum_id')->on('notenbaeume')->cascadeOnDelete();
            $table->foreign('eltern_id', 'fk_knoten_eltern')->references('knoten_id')->on('notenbaum_knoten')->cascadeOnDelete();
            $table->foreign('kategorie_id', 'fk_knoten_kategorie')->references('kategorie_id')->on('kategorien');
        });
        DB::statement("ALTER TABLE notenbaum_knoten ADD CONSTRAINT chk_knoten_typ CHECK (typ IN ('gruppe', 'kategorie', 'faecher', 'manuell'))");
        DB::statement("ALTER TABLE notenbaum_knoten ADD CONSTRAINT chk_knoten_elementtyp CHECK (elementtyp IN ('alle', 'modul', 'fach'))");
        DB::statement("ALTER TABLE notenbaum_knoten ADD CONSTRAINT chk_knoten_kategorie CHECK (typ <> 'kategorie' OR kategorie_id IS NOT NULL)");
        DB::statement('ALTER TABLE notenbaum_knoten ADD CONSTRAINT chk_knoten_werte CHECK (
            gewicht >= 0 AND (rundung IS NULL OR (rundung > 0 AND rundung <= 1))
            AND (fallnote IS NULL OR (fallnote >= 1 AND fallnote <= 6))
            AND (max_minuspunkte IS NULL OR max_minuspunkte >= 0)
        )');

        Schema::create('notenbaum_knoten_faecher', function (Blueprint $table) {
            $table->unsignedInteger('knoten_id');
            $table->unsignedInteger('fach_id');
            $table->primary(['knoten_id', 'fach_id']);
            $table->foreign('knoten_id', 'fk_knfach_knoten')->references('knoten_id')->on('notenbaum_knoten')->cascadeOnDelete();
            $table->foreign('fach_id', 'fk_knfach_fach')->references('fach_id')->on('faecher')->cascadeOnDelete();
        });

        Schema::create('notenbaum_positionen', function (Blueprint $table) {
            $table->increments('position_id');
            $table->unsignedInteger('lernender_id');
            $table->unsignedInteger('knoten_id');
            $table->decimal('note_wert', 4, 2);
            $table->date('datum')->nullable();
            $table->unsignedInteger('erfasst_von_benutzer_id');
            $table->unsignedInteger('aktualisiert_von_benutzer_id')->nullable();
            $table->dateTime('erstellt_am')->useCurrent();
            $table->dateTime('aktualisiert_am')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['lernender_id', 'knoten_id'], 'uk_position');
            $table->foreign('lernender_id', 'fk_position_lernender')->references('lernender_id')->on('lernende')->cascadeOnDelete();
            $table->foreign('knoten_id', 'fk_position_knoten')->references('knoten_id')->on('notenbaum_knoten')->cascadeOnDelete();
            $table->foreign('erfasst_von_benutzer_id', 'fk_position_erfasst')->references('benutzer_id')->on('benutzer');
            $table->foreign('aktualisiert_von_benutzer_id', 'fk_position_aktual')->references('benutzer_id')->on('benutzer');
        });
        DB::statement('ALTER TABLE notenbaum_positionen ADD CONSTRAINT chk_position_wert CHECK (note_wert >= 1.0 AND note_wert <= 6.0)');
    }

    public function down(): void
    {
        // Vor jeder DDL prüfen: MariaDB rollt DDL nicht zurück, ein Abbruch mittendrin liesse das Schema halb stehen.
        // Stufen (Sport A/B/C) passen nicht in das alte Schema; still löschen wäre Datenverlust.
        $stufen = DB::table('noten')->whereNull('note_wert')->count();
        if ($stufen > 0) {
            throw new RuntimeException("Rollback abgebrochen: {$stufen} Noten mit Stufe (Sport) vorhanden. Zuerst löschen oder in Zahlen umwandeln.");
        }

        Schema::dropIfExists('notenbaum_positionen');
        Schema::dropIfExists('notenbaum_knoten_faecher');
        Schema::dropIfExists('notenbaum_knoten');
        Schema::dropIfExists('notenbaeume');

        DB::statement('ALTER TABLE noten DROP CONSTRAINT IF EXISTS chk_noten_note_wert');
        Schema::table('noten', function (Blueprint $table) {
            $table->dropColumn('note_stufe');
            $table->decimal('note_wert', 4, 2)->nullable(false)->change();
        });
        DB::statement('ALTER TABLE noten ADD CONSTRAINT chk_noten_note_wert CHECK (note_wert >= 1.0 AND note_wert <= 6.0)');

        DB::statement('ALTER TABLE faecher DROP CONSTRAINT IF EXISTS chk_faecher_skala');
        Schema::table('faecher', function (Blueprint $table) {
            $table->dropColumn(['skala', 'zaehlt']);
        });
    }
};
