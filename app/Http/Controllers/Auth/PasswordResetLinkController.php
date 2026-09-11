<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker()->sendResetLink($request->only('email'));

        // Immer dieselbe neutrale Meldung, egal ob die Adresse existiert oder aktiv ist (keine Konto-Enumeration).
        return back()->with('status', 'Falls diese E-Mail-Adresse registriert ist, wurde ein Link zum Zurücksetzen verschickt.');
    }
}
