<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Services\Notifications\MailContent;

/** Die tägliche Datensicherung ist fehlgeschlagen – geht an alle aktiven Admins. */
final class BackupFailed
{
    public static function content(string $fehler): MailContent
    {
        return new MailContent(
            subject: 'Sicherung fehlgeschlagen',
            lines: ['Die tägliche Datensicherung ist heute fehlgeschlagen.'],
            facts: ['Fehler' => mb_substr($fehler, 0, 300)],
            outro: ['Bitte auf dem Server prüfen, sobald möglich.'],
            digestTitle: 'Sicherung fehlgeschlagen',
        );
    }
}
