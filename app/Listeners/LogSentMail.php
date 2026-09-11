<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MailLog;
use App\Notifications\PortalMail;
use Illuminate\Notifications\Events\NotificationSent;

/** Versandprotokoll: Mail an den Server übergeben → Status «Verschickt». */
class LogSentMail
{
    public function handle(NotificationSent $event): void
    {
        if ($event->notification instanceof PortalMail && $event->notification->logId) {
            MailLog::whereKey($event->notification->logId)->update([
                'status' => MailLog::SENT,
                'sent_at' => now(),
                'error' => null,
            ]);
        }
    }
}
