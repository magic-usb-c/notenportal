<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\Protokoll;
use Illuminate\Auth\Events\Logout;

/** Aktivitätsprotokoll: Abmeldung. */
class LogLogout
{
    public function handle(Logout $event): void
    {
        Protokoll::schreiben(Protokoll::AUTH_ABMELDUNG, $event->user instanceof User ? $event->user : null);
    }
}
