<?php

namespace Tests;

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
}
