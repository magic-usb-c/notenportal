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
                if (! is_string($m) || ! str_starts_with($m, 'throttle:')) {
                    continue;
                }
                $teile = explode(',', substr($m, strlen('throttle:')));
                if (count($teile) < 3 || $teile[2] === '') {
                    $ohnePraefix[] = ($route->getName() ?? $route->uri()).' → '.$m;
                } else {
                    $praefixe[$teile[2]][$route->getName() ?? $route->uri()] = $teile[0].','.$teile[1];
                }
            }
        }

        $this->assertSame([], $ohnePraefix, "throttle ohne Präfix (teilt den Zähler mit allen anderen Routen des Benutzers):\n".implode("\n", $ohnePraefix));
        $this->assertNotEmpty($praefixe);

        // Ein Präfix darf nur dort mehrfach stehen, wo derselbe Zähler gewollt ist: dieselbe Funktion für
        // Lernende und Verwaltung (ein Benutzer hat eine Rolle) oder Hin- und Rückweg einer Aktion – und
        // immer mit demselben Limit.
        $geteilt = ['calculator', 'import-read', 'import-validate', 'documents-store', 'data-export', 'feedback-vote'];
        $falsch = [];
        foreach ($praefixe as $praefix => $routen) {
            if (count($routen) > 1 && ! in_array($praefix, $geteilt, true)) {
                $falsch[] = $praefix.' doppelt: '.implode(', ', array_keys($routen));
            }
            if (count(array_unique($routen)) > 1) {
                $falsch[] = $praefix.' mit verschiedenen Limits: '.json_encode($routen);
            }
        }
        $this->assertSame([], $falsch, implode("\n", $falsch));
    }
}
