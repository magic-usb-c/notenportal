<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\Protokoll;
use Illuminate\Auth\Events\Login;

/** Aktivitätsprotokoll: erfolgreiche Anmeldung. */
class LogLogin
{
    public function handle(Login $event): void
    {
        Protokoll::schreiben(Protokoll::AUTH_ANMELDUNG_ERFOLGREICH, $event->user instanceof User ? $event->user : null);
    }
}
