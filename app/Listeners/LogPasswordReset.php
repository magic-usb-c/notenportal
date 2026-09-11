<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\Protokoll;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Aktivitätsprotokoll: Passwort geändert – ausgelöst in NewPasswordController::store(), sobald
 * der Reset (Broker «users» = «Passwort vergessen» oder «invites» = Konto-Mail/Admin-Reset) glückt.
 */
class LogPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        Protokoll::schreiben(Protokoll::AUTH_PASSWORT_GEAENDERT, $event->user instanceof User ? $event->user : null);
    }
}
