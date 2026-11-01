<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Feedback;
use App\Services\Notifications\MailContent;
use Illuminate\Support\Str;

/** Die eigene Meldung wurde beantwortet – oder nur ihr Status hat sich geändert (dann ohne Antwort-Behauptung). */
final class FeedbackAnswered
{
    /** $antwortNeu: Hat diese Änderung die Antwort gesetzt oder geändert? Ohne Angabe zählt jede vorhandene Notiz. */
    public static function content(Feedback $feedback, ?bool $antwortNeu = null): MailContent
    {
        $status = __(Feedback::STATUS[$feedback->status] ?? $feedback->status);
        $beantwortet = filled($feedback->admin_notiz) && ($antwortNeu ?? true);
        $datum = $feedback->erstellt_am?->format('d.m.Y') ?? '';

        return new MailContent(
            subject: $beantwortet ? __('Deine Meldung wurde beantwortet') : __('Deine Meldung ist jetzt «:status»', ['status' => $status]),
            // Betreff und Absatz nennen den Status schon, die Faktenzeile nur, wenn der Betreff die Antwort meldet
            lines: [$beantwortet
                ? __('Auf deine Meldung vom :datum gibt es eine Antwort.', ['datum' => $datum])
                : __('Der Status deiner Meldung vom :datum ist jetzt «:status».', ['datum' => $datum, 'status' => $status])],
            facts: $beantwortet ? [__('Status') => $status] : [],
            sections: $beantwortet ? [['title' => __('Antwort'), 'text' => $feedback->admin_notiz]] : [],
            actionLabel: __('Meine Meldungen'),
            actionUrl: route('feedback.index'),
            digestTitle: $beantwortet ? __('Deine Meldung wurde beantwortet') : __('Deine Meldung ist jetzt «:status»', ['status' => $status]),
            digestText: $beantwortet ? Str::limit((string) $feedback->admin_notiz, 300) : null,
        );
    }
}
