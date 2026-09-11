<?php

namespace App\Providers;

use App\Models\Feedback;
use App\Services\Notifications\MailSettings;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\Auth;
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

        // Mail-Einstellungen aus «Betrieb» überschreiben .env (vor Migrationen gibt es die Tabelle noch nicht)
        try {
            MailSettings::apply();
        } catch (\Throwable) {
        }

        View::composer(['layouts.app', 'layouts.guest', 'layouts.navigation', 'auth.login'], function ($view) {
            $view->with('betriebName', Einstellungen::get(Einstellungen::BETRIEB_NAME));
        });

        View::composer('layouts.navigation', function ($view) {
            $user = Auth::user();
            $anzahlOffen = ($user && $user->hasRole('Admin'))
                ? Feedback::where('status', Feedback::STATUS_OFFEN)->count()
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
