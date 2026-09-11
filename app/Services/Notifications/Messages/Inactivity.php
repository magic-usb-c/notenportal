<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Services\Notifications\MailContent;

/** Erinnerung, wenn seit einiger Zeit keine Note mehr eingetragen wurde. */
final class Inactivity
{
    public static function content(Lernender $lernender, int $tage): MailContent
    {
        $betreff = __('Seit :tage Tagen keine neue Note', ['tage' => $tage]);

        return new MailContent(
            subject: $betreff,
            lines: [__('Du hast seit :tage Tagen keine Note mehr eingetragen. Trag deine aktuellen Noten ein, damit dein Notenstand stimmt.', ['tage' => $tage])],
            actionLabel: __('Note erfassen'),
            actionUrl: route('learner.grades.create'),
            digestTitle: $betreff,
        );
    }
}
