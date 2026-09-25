<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jede Fehlerseite erscheint im Portal-Layout auf Deutsch – nie Laravels englische Standardseite.
 */
class FehlerseitenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_fehlerseite/{code}', fn (int $code) => abort($code))->middleware('web');
    }

    public static function fehler(): array
    {
        return [
            '404' => [404, 'Seite nicht gefunden'],
            '405 (Fallback 4xx)' => [405, 'Anfrage nicht möglich'],
            '429' => [429, 'Zu viele Anfragen'],
            '500' => [500, 'Serverfehler'],
            '502 (Fallback 5xx)' => [502, 'Serverfehler'],
            '503' => [503, 'Wartung'],
        ];
    }

    #[Test]
    #[DataProvider('fehler')]
    public function fehlerseite_ist_deutsch_im_portal_layout(int $code, string $titel): void
    {
        $this->get('/_fehlerseite/'.$code)
            ->assertStatus($code)
            ->assertSee($titel)
            ->assertSee(__('Zur Anmeldung'))
            ->assertDontSee('Too Many Requests')
            ->assertDontSee('Service Unavailable')
            ->assertDontSee('Method Not Allowed');
    }

    #[Test]
    public function falsche_methode_auf_bestehender_route_zeigt_deutsche_seite(): void
    {
        $this->delete(route('login'))
            ->assertStatus(405)
            ->assertSee('Anfrage nicht möglich');
    }

    #[Test]
    public function fehlerseite_erscheint_auch_ohne_datenbank(): void
    {
        $verbindung = config('database.default');
        $port = config("database.connections.{$verbindung}.port");
        config(["database.connections.{$verbindung}.port" => 1]);
        DB::purge($verbindung);

        try {
            $this->get('/_fehlerseite/500')
                ->assertStatus(500)
                ->assertSee('Serverfehler')
                ->assertSee(__('Zur Anmeldung'));
        } finally {
            config(["database.connections.{$verbindung}.port" => $port]);
            DB::purge($verbindung);
        }
    }

    #[Test]
    public function fehlerseite_erscheint_ohne_datenbank_auch_fuer_angemeldete(): void
    {
        $user = User::factory()->lernender()->create();
        $verbindung = config('database.default');
        $port = config("database.connections.{$verbindung}.port");
        config(["database.connections.{$verbindung}.port" => 1]);
        DB::purge($verbindung);

        try {
            $this->withSession([Auth::guard()->getName() => $user->getKey()])
                ->get('/_fehlerseite/500')
                ->assertStatus(500)
                ->assertSee('Serverfehler');
        } finally {
            config(["database.connections.{$verbindung}.port" => $port]);
            DB::purge($verbindung);
        }
    }
}
