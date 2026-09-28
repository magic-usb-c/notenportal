<?php

namespace Tests;

use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = BasisSeeder::class;

    /**
     * RefreshDatabase droppt alle Tabellen. Zeigt die Verbindung nicht auf eine
     * *_test-Datenbank, wird abgebrochen, bevor etwas gelöscht wird.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $datenbank = DB::connection()->getDatabaseName();

        if (! str_ends_with($datenbank, '_test')) {
            throw new RuntimeException("Tests laufen nur gegen eine *_test-Datenbank, nicht gegen «{$datenbank}».");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Einstellungen::alle() memoisiert statisch pro Request. Anders als der Cache (Treiber
        // «array») wird dieser Zwischenspeicher beim Hochfahren einer neuen Testanwendung nicht
        // automatisch geleert – ohne diesen Reset sähe ein Test die von einem vorigen Test über
        // set() gesetzten, danach per Transaktions-Rollback wieder verworfenen Werte.
        Einstellungen::vergessen();
    }
}
