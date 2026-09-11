<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Feedback;
use App\Models\User;
use App\Services\Notifications\MailContent;

/** Jemand hat Feedback, eine Idee oder einen Fehler gemeldet – geht an alle aktiven Admins. */
final class FeedbackReceived
{
    public static function content(Feedback $feedback, User $melder): MailContent
    {
        $name = trim($melder->vorname.' '.$melder->nachname);
        $rolle = $melder->rollen()->pluck('name')->first() ?? '–';
        $kategorie = Feedback::KATEGORIEN[$feedback->kategorie] ?? $feedback->kategorie;

        return new MailContent(
            subject: 'Neue Meldung («'.$kategorie.'») von '.$name,
            lines: [$name.' ('.$rolle.') hat eine Meldung erfasst.'],
            facts: array_filter(['Kategorie' => $kategorie, 'Seite' => $feedback->route_name ?? $feedback->url]),
            sections: [['title' => 'Text', 'text' => $feedback->text]],
            actionLabel: 'Meldung ansehen',
            actionUrl: route('admin.feedback.index'),
            digestTitle: 'Neue Meldung («'.$kategorie.'») von '.$name,
        );
    }
}
