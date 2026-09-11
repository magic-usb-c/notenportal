<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Darstellung;
use App\Support\Seitenzugriff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Vorgemerkte Ziel-URL nur, wenn die Rolle sie öffnen darf (sonst 403 nach dem Login).
        // Ohne sie: persönliche Startseite (Profil «Darstellung»), Standard «/dashboard».
        $ziel = $request->session()->pull('url.intended');
        if (is_string($ziel) && Seitenzugriff::erlaubt($request->user(), $ziel)) {
            return redirect()->to($ziel);
        }

        return redirect(route(Darstellung::startseiteRoute($request->user()), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Kontowechsel von der 403-Seite: nach der Anmeldung zurück zur Seite (nur relative Pfade)
        $weiter = $request->input('weiter');
        if (is_string($weiter) && str_starts_with($weiter, '/') && ! str_starts_with($weiter, '//')) {
            $request->session()->put('url.intended', $weiter);

            return redirect()->route('login');
        }

        return redirect('/')->with('status', __('Du wurdest abgemeldet.'));
    }
}
