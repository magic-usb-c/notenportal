<?php

namespace App\Providers;

use App\Support\Einstellungen;
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
    }
}
