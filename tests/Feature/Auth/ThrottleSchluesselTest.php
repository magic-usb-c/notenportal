<?php

namespace Tests\Feature\Auth;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * throttle:max,decay ohne drittes Argument schlüsselt bei angemeldeten Benutzern nur nach
 * Benutzer-ID (ThrottleRequests::resolveRequestSignature). Alle so gedrosselten Routen eines
 * Benutzers teilen sich dann einen Zähler: nach 11 Rechner-Aufrufen wäre das Feedback (10/min)
 * gesperrt gewesen (gemessen 01.10.2026). Deshalb trägt jede angemeldete throttle-Route einen
 * eigenen Präfix; Gast-Routen (Login, Passwort, signierte Links) schlüsseln nach IP und bleiben frei.
 */
class ThrottleSchluesselTest extends TestCase
{
    #[Test]
    public function jede_angemeldete_throttle_route_hat_einen_eigenen_praefix(): void
    {
        $ohnePraefix = [];
        $praefixe = [];
        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $middleware = $route->gatherMiddleware();
            if (! in_array('auth', $middleware, true)) {
                continue;
            }
            foreach ($middleware as $m) {
                if (! str_starts_with($m, 'throttle:')) {
                    continue;
                }
                $teile = explode(',', substr($m, strlen('throttle:')));
                if (count($teile) < 3 || $teile[2] === '') {
                    $ohnePraefix[] = ($route->getName() ?? $route->uri()).' → '.$m;
                } else {
                    $praefixe[$teile[2]][] = $route->getName() ?? $route->uri();
                }
            }
        }

        $this->assertSame([], $ohnePraefix, "throttle ohne Präfix (teilt den Zähler mit allen anderen Routen des Benutzers):\n".implode("\n", $ohnePraefix));
        $this->assertNotEmpty($praefixe);
    }
}
