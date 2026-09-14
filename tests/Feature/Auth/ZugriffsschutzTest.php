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
    private const array ROLLEN = [
        'Admin' => 'admin',
        'Berufsbildner' => 'berufsbildner',
        'Lernender' => 'lernender',
    ];

    /** Routen ohne Rollenbindung; Berechtigung prüft der Controller selbst. */
    private const array OHNE_ROLLE = [
        'dashboard',
        'profile.legacy',
        'settings.index',
        'settings.profile',
        'settings.calendar',
        'settings.calendar.token.reset',
        'settings.data',
        'profile.update',
        'profile.appearance',
        'profile.preferences',
        'profile.preferences.reset',
        'profile.data-export',
        'password.update',
        'password.initial',
        'password.initial.update',
        'password.confirm',
        'password.confirm.store',
        'logout',
        'comments.store',
        'comments.destroy',
        'feedback.store',
        'feedback.index',
        'feedback.hint.dismiss',
        'feedback.similar',
        'feedback.vote',
        'feedback.vote.destroy',
        'feedback.attachment',
        'system-notice.dismiss',
        'session.keep-alive',
        'search',
        'notifications.settings',
        'notifications.settings.update',
        // Module sind gemeinsame Stammdaten: ansehen, anlegen, ergänzen und Unterlagen beisteuern
        // steht jeder angemeldeten Person offen, unabhängig von der Rolle. Nur modules.enroll
        // (Modul in die eigene Notenerfassung holen) trägt role:Lernender und fehlt hier deshalb.
        'modules.index',
        'modules.create',
        'modules.store',
        'modules.show',
        'modules.edit',
        'modules.update',
        'modules.documents.store',
        'modules.documents.show',
        'modules.documents.destroy',
    ];

    /** Öffentlich erreichbar (kein Login nötig). */
    private const array OEFFENTLICH = [
        '/', 'login', 'up',
        'notifications.unsubscribe', 'notifications.unsubscribe.store',
        'password.request', 'password.email', 'password.reset', 'password.store',
        'calendar.export',
        'manifest', 'offline', // PWA: Web-App-Manifest und Offline-Seite, öffentlich, ohne Personendaten
        'branding.logo', // Betriebslogo: öffentlich, ohne Login, für Login-Seite und Mail-Kopf
        'profile.locale', // Sprachwahl: Gäste nur für die Session, 404 solange sprachwahl_aktiv aus ist
        '{fallbackPlaceholder}', // alte deutsche Pfade → 301 (LegacyPaths), sonst 404
    ];

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
                    $routen[] = [$route, array_map(trim(...), explode(',', substr($middleware, 5)))];
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
