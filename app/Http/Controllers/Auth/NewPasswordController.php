<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email'),
        ]);
    }

    /**
     * Setzt das Passwort. Token kommt entweder aus «Passwort vergessen» (Broker «users»,
     * 60 Minuten, Tabelle password_reset_tokens) oder aus einer Konto-Mail (Broker «invites»,
     * 7 Tage, Tabelle password_invite_tokens). Zuerst «users» versuchen, sonst «invites».
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $callback = function (User $user) use ($request): void {
            $user->forceFill([
                'passwort_hash' => $request->string('password')->value(),
                'passwort_wechsel_noetig' => false,
            ]);
            if (Schema::hasColumn('benutzer', 'remember_token')) {
                $user->remember_token = Str::random(60);
            }
            $user->save();

            Notifier::send($user, NotificationCatalog::PASSWORD_CHANGED, fn () => new MailContent(
                subject: 'Dein Passwort wurde geändert',
                title: 'Dein Passwort wurde geändert',
                facts: ['Zeitpunkt' => now()->format('d.m.Y H:i')],
                outro: ['Warst du das nicht? Dann melde dich bei einem Admin.'],
            ));
        };

        $status = Password::broker('users')->reset($credentials, $callback);
        if ($status !== Password::PASSWORD_RESET) {
            $status = Password::broker('invites')->reset($credentials, $callback);
        }

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
