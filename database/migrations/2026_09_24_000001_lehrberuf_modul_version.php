<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalogversion je Zuordnung Beruf→Modul statt nur je Modul.
 *
 * `module.modul_nummer` ist eindeutig, eine Modulzeile führt also genau eine Version. Im Katalog
 * sind aber 17 der 334 Nummern in zwei gleichzeitig gültigen Versionen im Umlauf (z. B. 117 in V4
 * für die Bildungsverordnung 2021 und V5 für 2026), weil jeder Jahrgang seine eigene führt. Der
 * Verweis «Im Modulbaukasten öffnen» zeigte darum für den älteren Jahrgang auf die neuere Version.
 *
 * Die Spalte hält die Version, die der Katalog für genau diesen Beruf nennt; der Katalogimport
 * füllt sie aus der Abschlussliste. Bleibt sie leer, gilt weiterhin `module.version` – bestehende
 * Zeilen verhalten sich also unverändert, bis eine neue Ernte eingelesen wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lehrberuf_module', function (Blueprint $table) {
            $table->string('version', 10)->nullable()->after('pflichtgrad');
        });
    }

    public function down(): void
    {
        Schema::table('lehrberuf_module', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
