<?php

namespace Tests;

use App\Services\Auswertung\Konfiguration;
use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
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
        // Dasselbe gilt für Stammdaten und Notenbäume, die der Rechenkern statisch zwischenspeichert.
        Konfiguration::vergessen();
    }

    /**
     * AuthenticateSession (web-Gruppe) merkt sich den Passwort-Hash des angemeldeten Benutzers in der Sitzung. Die
     * Testsitzung (Treiber «array») lebt über mehrere Anfragen eines Tests; wechselt ein Test mit actingAs() den
     * Benutzer, würde der Hash des vorigen als «anderes Gerät» gelten und die Anfrage abmelden. Jeder actingAs()-Aufruf
     * beginnt darum ohne gemerkten Hash (die Middleware trägt ihn bei der nächsten Anfrage wieder ein).
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $sitzung = $this->app['session']->driver();
        $sitzung->forget('password_hash_'.($guard ?? 'web'));
        // Die Anfrage lädt beim Start die zuletzt gespeicherte Kopie aus dem Handler und mischt sie zurück.
        $sitzung->getHandler()->destroy($sitzung->getId());

        return parent::actingAs($user, $guard);
    }
}
