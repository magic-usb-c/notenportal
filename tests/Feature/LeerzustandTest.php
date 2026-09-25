<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Frisch angelegte Konten ohne jede Daten (Lernende ohne Noten, Berufsbildner ohne
 * Lernende, Admin auf leerer Installation): jede parameterlose GET-Seite der eigenen
 * Rolle muss ohne Serverfehler rendern.
 */
class LeerzustandTest extends TestCase
{
    /** Rollenfreie Seiten, die jede angemeldete Person aufruft. */
    private const array OHNE_ROLLE = [
        'dashboard', 'settings.index', 'settings.profile', 'settings.calendar', 'settings.data',
        'feedback.index', 'search', 'notifications.settings', 'modules.index', 'modules.create',
    ];

    public static function rollen(): array
    {
        return [
            'Admin' => ['Admin', 'admin'],
            'Berufsbildner' => ['Berufsbildner', 'berufsbildner'],
            'Lernender' => ['Lernender', 'lernender'],
        ];
    }

    #[Test]
    #[DataProvider('rollen')]
    public function jede_seite_rendert_ohne_daten(string $rolle, string $factoryZustand): void
    {
        $benutzer = User::factory()->{$factoryZustand}()->create();
        $geprueft = 0;

        foreach (Route::getRoutes() as $route) {
            if (! $this->istSeiteFuer($route, $rolle)) {
                continue;
            }

            $antwort = $this->actingAs($benutzer)->get('/'.ltrim($route->uri(), '/'));
            $this->assertLessThan(500, $antwort->getStatusCode(), "{$rolle}: GET /{$route->uri()}");
            $geprueft++;
        }

        $this->assertGreaterThan(5, $geprueft);
    }

    private function istSeiteFuer(RoutingRoute $route, string $rolle): bool
    {
        if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            return false;
        }

        if (in_array($route->getName(), self::OHNE_ROLLE, true)) {
            return true;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')
                && in_array($rolle, array_map(trim(...), explode(',', substr($middleware, 5))), true)) {
                return true;
            }
        }

        return false;
    }
}
