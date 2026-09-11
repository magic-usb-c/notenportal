<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $user->update([
            'passwort_hash' => Hash::make($validated['password']),
        ]);

        // Kein PasswordReset-Event hier (nur in NewPasswordController::store()) – kein Doppel-Eintrag mit LogPasswordReset.
        Protokoll::schreiben(Protokoll::AUTH_PASSWORT_GEAENDERT, $user);

        Notifier::send($user, NotificationCatalog::PASSWORD_CHANGED, fn () => new MailContent(
            subject: __('Dein Passwort wurde geändert'),
            title: __('Dein Passwort wurde geändert'),
            facts: [__('Zeitpunkt') => now()->format('d.m.Y H:i')],
            outro: [__('Warst du das nicht? Dann melde dich bei einem Admin.')],
        ));

        return back()->with('success', __('Passwort aktualisiert.'));
    }
}
