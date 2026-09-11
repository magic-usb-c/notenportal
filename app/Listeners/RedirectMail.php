<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MailLog;
use App\Services\Notifications\MailSettings;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Mail-Umleitung (Betrieb → E-Mail): solange gesetzt, gehen alle Mails an diese Adresse,
 * der ursprüngliche Empfänger steht im Betreff. Für Test- und Pilotphase.
 */
class RedirectMail
{
    public function handle(MessageSending $event): void
    {
        $ziel = MailSettings::redirectTo();
        if (! $ziel) {
            return;
        }

        $message = $event->message;
        $original = implode(', ', array_map(fn (Address $a) => $a->getAddress(), $message->getTo()));
        $message->to($ziel);
        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');
        $message->subject('['.$original.'] '.$message->getSubject());

        $logId = $message->getHeaders()->get('X-Notenportal-Log')?->getBodyAsString();
        if ($logId) {
            MailLog::whereKey((int) $logId)->update(['redirected_to' => $ziel]);
        }
    }
}
