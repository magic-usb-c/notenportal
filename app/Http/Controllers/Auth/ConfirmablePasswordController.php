<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswortOrakelSperre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Erneute Passwort-Bestätigung (Breeze-Standard) für heikle Aktionen, z. B. die eigene Datenauskunft. */
class ConfirmablePasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        PasswortOrakelSperre::pruefen($request, 'password');

        try {
            $request->validate([
                'password' => ['required', 'current_password'],
            ]);
        } catch (ValidationException $e) {
            if ($request->filled('password')) {
                PasswortOrakelSperre::fehlversuch($request);
            }

            throw $e;
        }

        PasswortOrakelSperre::zuruecksetzen($request);

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
