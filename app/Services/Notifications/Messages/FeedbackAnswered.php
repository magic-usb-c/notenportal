<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Feedback;
use App\Services\Notifications\MailContent;

/** Die eigene Meldung wurde beantwortet – oder nur ihr Status hat sich geändert (dann ohne Antwort-Behauptung). */
final class FeedbackAnswered
{
    /** $antwortNeu: Hat diese Änderung die Antwort gesetzt oder geändert? Ohne Angabe zählt jede vorhandene Notiz. */
    public static function content(Feedback $feedback, ?bool $antwortNeu = null): MailContent
    {
        $status = __(Feedback::STATUS[$feedback->status] ?? $feedback->status);
        $beantwortet = filled($feedback->admin_notiz) && ($antwortNeu ?? true);

        return new MailContent(
            subject: $beantwortet ? __('Deine Meldung wurde beantwortet') : __('Deine Meldung ist jetzt «:status»', ['status' => $status]),
            lines: [$beantwortet
                ? __('Deine Meldung hat den Status «:status».', ['status' => $status])
                : __('Der Status deiner Meldung ist jetzt «:status».', ['status' => $status])],
            facts: [__('Status') => $status],
            sections: $beantwortet ? [['title' => __('Antwort'), 'text' => $feedback->admin_notiz]] : [],
            actionLabel: __('Meine Meldungen'),
            actionUrl: route('feedback.index'),
            digestTitle: __('Deine Meldung ist jetzt «:status»', ['status' => $status]),
        );
    }
}
