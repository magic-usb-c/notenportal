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
 * gesperrt gewesen (gemessen 01.10.2026). Gäste schlüsseln nach IP, teilen aber genauso einen Zähler:
 * nach fünf Login-Versuchen war «Passwort vergessen» (5/min) gesperrt (Prüfer 01.10.2026). Deshalb trägt
 * jede throttle-Route einen eigenen Präfix.
 */
class ThrottleSchluesselTest extends TestCase
{
    #[Test]
    public function jede_throttle_route_hat_einen_eigenen_praefix(): void
    {
        $ohnePraefix = [];
        $praefixe = [];
        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            foreach ($route->gatherMiddleware() as $m) {
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

        $this->assertSame([], $ohnePraefix, "throttle ohne Präfix (teilt den Zähler mit allen anderen Routen des Benutzers oder der IP):\n".implode("\n", $ohnePraefix));
        $this->assertNotEmpty($praefixe);

        // Ein Präfix darf nur dort mehrfach stehen, wo derselbe Zähler gewollt ist: dieselbe Funktion für
        // Lernende und Verwaltung (ein Benutzer hat eine Rolle) oder Hin- und Rückweg einer Aktion – und
        // immer mit demselben Limit.
        $geteilt = ['calculator', 'import-read', 'import-validate', 'documents-store', 'feedback-vote', 'unsubscribe'];
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

    #[Test]
    public function fehlversuche_beim_login_sperren_passwort_vergessen_nicht(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'niemand@example.local', 'password' => 'falsch'])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'niemand@example.local'])->assertStatus(302);
    }
}
