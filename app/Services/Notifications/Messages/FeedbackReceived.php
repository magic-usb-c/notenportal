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
        $kategorie = __(Feedback::kategorieLabel($feedback->kategorie));
        // Nur der Pfad der Seite: weder Routenname (Entwicklersprache) noch Host oder Query-String (können Tokens tragen).
        $seite = $feedback->url ? parse_url($feedback->url, PHP_URL_PATH) : null;

        return new MailContent(
            subject: __('Neue Meldung («:kategorie») von :name', ['kategorie' => $kategorie, 'name' => $name]),
            lines: [__(':name (:rolle) hat eine Meldung erfasst.', ['name' => $name, 'rolle' => $rolle])],
            facts: array_filter([__('Kategorie') => $kategorie, __('Seite') => is_string($seite) ? $seite : null]),
            sections: [['title' => __('Text'), 'text' => $feedback->text]],
            actionLabel: __('Meldung ansehen'),
            actionUrl: route('admin.feedback.index'),
            digestTitle: __('Neue Meldung («:kategorie») von :name', ['kategorie' => $kategorie, 'name' => $name]),
        );
    }
}
