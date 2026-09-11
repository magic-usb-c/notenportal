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
            subject: __('Deine Meldung wurde beantwortet'),
            lines: [__('Deine Meldung hat den Status «:status».', ['status' => $statusLabel])],
            facts: [__('Status') => $statusLabel],
            sections: $feedback->admin_notiz ? [['title' => __('Antwort'), 'text' => $feedback->admin_notiz]] : [],
            actionLabel: __('Meine Meldungen'),
            actionUrl: route('feedback.index'),
            digestTitle: __('Deine Meldung ist jetzt «:status»', ['status' => $statusLabel]),
        );
    }
}
