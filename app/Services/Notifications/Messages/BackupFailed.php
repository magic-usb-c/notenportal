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
            subject: __('Sicherung fehlgeschlagen'),
            lines: [__('Die tägliche Datensicherung ist heute fehlgeschlagen.')],
            facts: [__('Fehler') => mb_substr($fehler, 0, 300)],
            outro: [__('Bitte auf dem Server prüfen, sobald möglich.')],
            digestTitle: __('Sicherung fehlgeschlagen'),
        );
    }
}
