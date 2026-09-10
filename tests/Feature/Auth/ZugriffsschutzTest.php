<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rollenebene: Jede Route braucht auth + role:-Middleware (oder steht bewusst
 * auf der Liste unten), und jede role:-Route ist für fremde Rollen gesperrt.
 * Datensatzebene (fremde Lernende, fremde Noten) prüfen eigene Tests.
 */
class ZugriffsschutzTest extends TestCase
{
    private const ROLLEN = [
        'Admin' => 'admin',
        'Berufsbildner' => 'berufsbildner',
        'Lernender' => 'lernender',
    ];

    /** Routen ohne Rollenbindung; Berechtigung prüft der Controller selbst. */
    private const OHNE_ROLLE = [
        'dashboard',
        'profile.edit',
        'profile.update',
        'profile.darstellung',
        'password.update',
        'passwort.wechsel',
        'passwort.wechsel.speichern',
        'logout',
        'noten.kommentare.store',
        'noten.kommentare.destroy',
        'feedback.store',
        'feedback.index',
    ];

    /** Öffentlich erreichbar. */
    private const OEFFENTLICH = ['/', 'login', 'up'];

    #[Test]
    public function jede_route_ist_geschuetzt_oder_bewusst_freigegeben(): void
    {
        $ungeschuetzt = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '/'.ltrim($route->uri(), '/');
            $middleware = $route->gatherMiddleware();
            $hatRolle = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'role:'));

            if (in_array($route->uri(), self::OEFFENTLICH, true) || in_array($name, self::OEFFENTLICH, true)) {
                continue;
            }

            if (! in_array('auth', $middleware, true) || (! $hatRolle && ! in_array($name, self::OHNE_ROLLE, true))) {
                $ungeschuetzt[] = implode('|', $route->methods()).' /'.$route->uri();
            }
        }

        $this->assertSame([], $ungeschuetzt, 'Routen ohne auth/role: '.implode(', ', $ungeschuetzt));
    }

    #[Test]
    public function gaeste_werden_zum_login_umgeleitet(): void
    {
        foreach ($this->rollenRouten() as [$route]) {
            foreach ($this->methoden($route) as $methode) {
                $this->call($methode, $this->uri($route))->assertRedirect(route('login'));
            }
        }
    }

    #[Test]
    public function rollenrouten_sind_fuer_andere_rollen_gesperrt(): void
    {
        $benutzer = array_map(fn (string $state) => User::factory()->{$state}()->create(), self::ROLLEN);
        $geprueft = 0;

        foreach ($this->rollenRouten() as [$route, $erlaubt]) {
            foreach (array_diff(array_keys(self::ROLLEN), $erlaubt) as $rolle) {
                foreach ($this->methoden($route) as $methode) {
                    $status = $this->actingAs($benutzer[$rolle])->call($methode, $this->uri($route))->getStatusCode();

                    $this->assertSame(403, $status, "{$rolle}: {$methode} /{$route->uri()}");
                    $geprueft++;
                }
            }
        }

        $this->assertGreaterThan(100, $geprueft);
    }

    /** @return list<array{0: RoutingRoute, 1: list<string>}> */
    private function rollenRouten(): array
    {
        $routen = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                    $routen[] = [$route, array_map('trim', explode(',', substr($middleware, 5)))];
                }
            }
        }

        return $routen;
    }

    /** @return list<string> */
    private function methoden(RoutingRoute $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD']));
    }

    private function uri(RoutingRoute $route): string
    {
        return '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
    }
}
