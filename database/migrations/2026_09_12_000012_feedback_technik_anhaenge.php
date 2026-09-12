<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Block G (Feedback III): Kategorie «Lob» → «Sonstiges» (Datenbankwert lob → sonstiges, gleiches
 * Drei-Schritt-Vorgehen wie 2026_09_11_000006: erweitern → umschlüsseln → einengen), technische
 * Angaben als JSON-Spalte (Block-Details in App\Http\Controllers\FeedbackController) sowie eigene
 * Anhänge (Tabelle feedback_anhaenge, bis zu drei je Meldung, siehe App\Services\Feedback\Anhang).
 *
 * Jeder Teil einzeln mit Guard, weil MariaDB-DDL nicht transaktional ist – ein abgebrochener Lauf
 * lässt sich so gefahrlos wiederholen. NICHT auf Prod ausführen (siehe Auftrag).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->kategorieMigriert()) {
            DB::statement("ALTER TABLE feedback MODIFY kategorie ENUM('fehler','idee','frage','lob','sonstiges') NOT NULL");
            DB::table('feedback')->where('kategorie', 'lob')->update(['kategorie' => 'sonstiges']);
            DB::statement("ALTER TABLE feedback MODIFY kategorie ENUM('fehler','idee','frage','sonstiges') NOT NULL");
        }

        if (! Schema::hasColumn('feedback', 'technik_details')) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->json('technik_details')->nullable()->after('js_fehler');
            });
        }

        if (! Schema::hasTable('feedback_anhaenge')) {
            Schema::create('feedback_anhaenge', function (Blueprint $table) {
                $table->increments('feedback_anhang_id');
                $table->unsignedInteger('feedback_id');
                $table->string('dateiname', 255);
                $table->string('pfad', 255);
                $table->string('mime', 100);
                $table->unsignedInteger('groesse');
                $table->dateTime('erstellt_am')->useCurrent();

                $table->index('feedback_id', 'idx_feedback_anhang_feedback');

                $table->foreign('feedback_id', 'fk_feedback_anhang_feedback')
                    ->references('feedback_id')->on('feedback')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_anhaenge');

        if (Schema::hasColumn('feedback', 'technik_details')) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->dropColumn('technik_details');
            });
        }

        if ($this->kategorieMigriert()) {
            // Schreibt ALLE «sonstiges»-Zeilen auf «lob» zurück, auch echte Neuzugänge, die nach der
            // Migration mit dem neuen Wert erfasst wurden (nicht nur die umgeschlüsselten Altdaten) –
            // ein Rollback dieser Migration verwischt also diesen Unterschied unwiderruflich.
            DB::statement("ALTER TABLE feedback MODIFY kategorie ENUM('fehler','idee','frage','lob','sonstiges') NOT NULL");
            DB::table('feedback')->where('kategorie', 'sonstiges')->update(['kategorie' => 'lob']);
            DB::statement("ALTER TABLE feedback MODIFY kategorie ENUM('fehler','idee','frage','lob') NOT NULL");
        }
    }

    /** True, wenn die Spalte kategorie schon vollständig auf «sonstiges» umgestellt ist (kein «lob» mehr im Enum). */
    private function kategorieMigriert(): bool
    {
        $typ = (string) DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'feedback')
            ->where('COLUMN_NAME', 'kategorie')
            ->value('COLUMN_TYPE');

        return str_contains($typ, "'sonstiges'") && ! str_contains($typ, "'lob'");
    }
};
