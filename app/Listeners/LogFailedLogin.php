<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\Protokoll;
use Illuminate\Auth\Events\Failed;

/**
 * Aktivitätsprotokoll: fehlgeschlagene Anmeldung. $event->user ist bereits durch
 * retrieveByCredentials() aufgelöst (nur wenn E-Mail + aktiv + nicht gelöscht passen) – gehört der
 * Versuch zu keinem Konto, wird die eingegebene E-Mail NICHT protokolliert (nur ein Hinweis).
 */
class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $konto = $event->user instanceof User ? $event->user : null;

        Protokoll::schreiben(
            Protokoll::AUTH_ANMELDUNG_FEHLGESCHLAGEN,
            $konto,
            $konto ? [] : ['konto' => __('unbekanntes Konto')]
        );
    }
}
