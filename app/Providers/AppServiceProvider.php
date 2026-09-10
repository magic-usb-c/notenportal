<?php

namespace App\Providers;

use App\Models\Feedback;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        View::composer(['layouts.app', 'layouts.guest', 'auth.login'], function ($view) {
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
            $view->with('bereich', in_array($bereich, ['admin', 'berufsbildner'], true) ? $bereich : 'admin');
        });
    }
}
