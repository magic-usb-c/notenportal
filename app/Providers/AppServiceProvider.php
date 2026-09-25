<?php

namespace App\Providers;

use App\Models\Feedback;
use App\Services\Notifications\MailSettings;
use App\Support\Darstellung;
use App\Support\Einstellungen;
use App\Support\Sitzung;
use App\Support\Systemhinweis;
use App\Support\Theme;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Übersetzungen pro Bereich (lang/areas/{bereich}/en.json), zusätzlich zu lang/en.json
        foreach (['learner', 'trainer', 'admin'] as $bereich) {
            Lang::addJsonPath(lang_path("areas/{$bereich}"));
        }

        // Mail-Einstellungen aus «Betrieb» überschreiben .env (vor Migrationen gibt es die Tabelle noch nicht)
        try {
            MailSettings::apply();
        } catch (\Throwable) {
        }

        // Sitzungs-Timeout aus «Betrieb»: läuft sicher vor StartSession (Provider, nicht Middleware-Reihenfolge).
        try {
            Sitzung::anwenden();
        } catch (\Throwable) {
        }

        View::composer(['layouts.app', 'layouts.guest', 'layouts.navigation', 'auth.login'], function ($view) {
            $view->with('betriebName', Einstellungen::get(Einstellungen::BETRIEB_NAME));
        });

        View::composer('layouts.app', function ($view) {
            $view->with('systemhinweis', Systemhinweis::aktuell(Auth::user()));
        });

        View::composer('auth.login', function ($view) {
            $view->with('loginHinweis', Systemhinweis::fuerLogin());
        });

        // Farbthema und persönliche Darstellung serverseitig ins <html> (kein Flackern).
        // errors.layout: Fehlerseiten (403/404/500) sieht auch ein angemeldeter Benutzer.
        View::composer(['layouts.app', 'layouts.guest', 'errors.layout'], function ($view) {
            // Ohne Datenbank (z. B. beim 500er) die Fehlerseite trotzdem rendern – dann als Gast.
            $user = rescue(fn () => Auth::user(), null, false);
            $theme = Theme::fuer($user);
            $praeferenzen = Darstellung::fuer($user);

            $view->with('npTheme', $theme);
            // Beim effektiven Theme «kontrast» werden data-akzent und die eigene Farbe nicht gesetzt.
            $view->with('npAkzent', $theme === Theme::KONTRAST ? null : $praeferenzen['akzent']);
            $view->with('npAkzentEigen', $theme === Theme::KONTRAST ? null : $praeferenzen['akzent_eigen']);
            $view->with('npSchrift', $praeferenzen['schrift']);
            $view->with('npSchriftart', $praeferenzen['schriftart']);
            $view->with('npBewegung', $praeferenzen['bewegung']);
            $view->with('npDichte', $praeferenzen['dichte']);
            $view->with('npDiagramm', $praeferenzen['diagramm']);
            $view->with('npEcken', $praeferenzen['ecken']);
            $view->with('npTransparenz', $praeferenzen['transparenz']);
        });

        View::composer('layouts.navigation', function ($view) {
            $user = Auth::user();
            $anzahlOffen = ($user && $user->hasRole('Admin'))
                ? Feedback::hauptmeldungen()->where('status', Feedback::STATUS_OFFEN)->count()
                : 0;

            $view->with('feedbackOffenCount', $anzahlOffen);
        });

        // Verwaltungs-Views sind rollenneutral: Links werden als route("{$bereich}.…") gebaut
        View::composer('verwaltung.*', function ($view) {
            $bereich = Str::before((string) Route::currentRouteName(), '.');
            $view->with('bereich', in_array($bereich, ['admin', 'trainer'], true) ? $bereich : 'admin');
        });
    }
}
