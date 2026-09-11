<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Feedback;
use App\Services\Notifications\MailContent;

/** Die eigene Meldung wurde beantwortet oder ihr Status hat sich geändert. */
final class FeedbackAnswered
{
    public static function content(Feedback $feedback): MailContent
    {
        $statusLabel = Feedback::STATUS[$feedback->status] ?? $feedback->status;

        return new MailContent(
            subject: 'Deine Meldung wurde beantwortet',
            lines: ['Deine Meldung hat den Status «'.$statusLabel.'».'],
            facts: ['Status' => $statusLabel],
            sections: $feedback->admin_notiz ? [['title' => 'Antwort', 'text' => $feedback->admin_notiz]] : [],
            actionLabel: 'Meine Meldungen',
            actionUrl: route('feedback.index'),
            digestTitle: 'Deine Meldung ist jetzt «'.$statusLabel.'»',
        );
    }
}
