<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SicherheitsHeader;
use App\Support\Sitzung;
use App\Support\TrustedHostPatterns;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Host-Header nur aus TRUSTED_HOSTS und APP_URL (samt Subdomains) annehmen – der Installer schreibt nach
        // TRUSTED_HOSTS alle Namen und Adressen aus dem Zertifikat: sonst liesse sich über einen fremden Host die
        // Adresse in erzeugten Links (Passwort-Reset, Mails) unterschieben. Solange TRUSTED_HOSTS leer ist (etwa
        // nach `git pull` ohne Installer), bleibt jeder Host erlaubt, damit kein Alias plötzlich 400 bekommt;
        // `notenportal:bereitschaft` warnt dann. Lokal und in Tests aus (Laravel-Standard).
        $middleware->trustHosts(at: fn () => TrustedHostPatterns::patterns(), subdomains: false);

        // Global statt in der web-Gruppe: so bekommen auch Redirects und Fehlerseiten aus Exceptions die Header.
        $middleware->append(SicherheitsHeader::class);

        $middleware->appendToGroup('web', [
            SetLocale::class,
            EnsureUserIsActive::class,
            EnsurePasswordChanged::class,
        ]);

        // Ein Passwortwechsel (Profil, Reset, Einladung, Zurücksetzen durch einen Admin) beendet alle anderen
        // Sitzungen des Kontos: der Hash des Passworts liegt (als HMAC) in der Sitzung und wird bei jeder Anfrage
        // verglichen. Treiberunabhängig (file wie database), ohne logoutOtherDevices(). Beendete Sitzungen
        // bekommen auf Seiten den Redirect zur Anmeldung, auf JSON-Anfragen 401 (sitzung.js meldet dann ab).
        // Steht nach SetLocale/EnsureUserIsActive: dort wird $request->user() ohnehin schon gelesen.
        $middleware->appendToGroup('web', AuthenticateSession::class);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // Abgelaufene Sitzung (419): statt der Fehlerseite zurück zum Formular, mit Hinweis.
        // JSON-Anfragen behalten 419 – die bestehenden fetch-Aufrufer behandeln das schon.
        // Hinweis: der Handler wandelt TokenMismatchException schon vor renderViaCallbacks() in eine
        // generische HttpException(419) um (siehe Handler::prepareException()), darum hier auf den Status prüfen.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $meldung = Sitzung::abgelaufenMeldung();

            if (! $request->user()) {
                return redirect()->route('login')
                    ->with('status', $meldung)
                    ->withInput($request->only('email'));
            }

            return back()
                ->with('error', $meldung)
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'passwort', 'passwort_confirmation', 'mail_password', '_token']));
        });
    })->create();
