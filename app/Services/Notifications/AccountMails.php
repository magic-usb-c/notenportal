<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\Password;

/**
 * Mails rund um Konten: Eröffnung und Admin-Reset. Nutzt den Broker «invites»
 * (config/auth.php), gültig 7 Tage – im Gegensatz zum kurzlebigen «Passwort
 * vergessen»-Link (Broker «users», siehe User::sendPasswordResetNotification).
 */
final class AccountMails
{
    public static function accountCreated(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        Notifier::dispatch($user, NotificationCatalog::ACCOUNT_CREATED, new MailContent(
            subject: 'Dein Konto im Notenportal',
            title: 'Dein Konto im Notenportal',
            lines: ['Für dich wurde ein Konto im Notenportal eingerichtet.'],
            facts: [
                'Login-E-Mail' => (string) $user->email,
                'Rolle'.($user->rollen()->count() > 1 ? 'n' : '') => $user->rollen()->pluck('name')->implode(', '),
                'Betrieb' => (string) Einstellungen::get(Einstellungen::BETRIEB_NAME, ''),
            ],
            actionLabel: 'Passwort festlegen',
            actionUrl: route('password.reset', ['token' => $token, 'email' => $user->email]),
        ));
    }

    public static function passwordResetByAdmin(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        Notifier::dispatch($user, NotificationCatalog::PASSWORD_RESET, new MailContent(
            subject: 'Dein Passwort wurde zurückgesetzt',
            title: 'Dein Passwort wurde zurückgesetzt',
            lines: ['Dein Passwort wurde zurückgesetzt.'],
            actionLabel: 'Neues Passwort festlegen',
            actionUrl: route('password.reset', ['token' => $token, 'email' => $user->email]),
        ));
    }
}
