<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Support\Einstellungen;
use App\Support\Protokoll;
use Illuminate\Support\Facades\Password;

/**
 * Mails rund um Konten: Eröffnung und Admin-Reset. Nutzt den Broker «invites»
 * (config/auth.php), gültig 7 Tage – im Gegensatz zum kurzlebigen «Passwort
 * vergessen»-Link (Broker «users», siehe User::sendPasswordResetNotification).
 * Inhalt als Closure: subject/title/facts werden über __() gebaut, in der Sprache
 * des Empfängers (Notifier setzt die Locale, bevor die Closure aufgerufen wird).
 */
final class AccountMails
{
    public static function accountCreated(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        Notifier::dispatch($user, NotificationCatalog::ACCOUNT_CREATED, fn () => new MailContent(
            subject: __('Dein Konto im Notenportal'),
            title: __('Dein Konto im Notenportal'),
            lines: [__('Für dich wurde ein Konto im Notenportal eingerichtet.')],
            facts: [
                __('Login-E-Mail') => (string) $user->email,
                ($user->rollen()->count() > 1 ? __('Rollen') : __('Rolle')) => $user->rollen()->pluck('name')->implode(', '),
                __('Betrieb') => (string) Einstellungen::get(Einstellungen::BETRIEB_NAME, ''),
            ],
            actionLabel: __('Passwort festlegen'),
            actionUrl: route('password.reset', ['token' => $token, 'email' => $user->email]),
        ));
    }

    public static function passwordResetByAdmin(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        Notifier::dispatch($user, NotificationCatalog::PASSWORD_RESET, fn () => new MailContent(
            subject: __('Dein Passwort wurde zurückgesetzt'),
            title: __('Dein Passwort wurde zurückgesetzt'),
            lines: [__('Dein Passwort wurde zurückgesetzt.')],
            actionLabel: __('Neues Passwort festlegen'),
            actionUrl: route('password.reset', ['token' => $token, 'email' => $user->email]),
        ));

        Protokoll::schreiben(Protokoll::ADMIN_RESET_LINK_GESCHICKT, $user);
    }
}
