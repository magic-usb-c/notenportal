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
            subject: __('Neue Meldung («:kategorie») von :name', ['kategorie' => $kategorie, 'name' => $name]),
            lines: [__(':name (:rolle) hat eine Meldung erfasst.', ['name' => $name, 'rolle' => $rolle])],
            facts: array_filter([__('Kategorie') => $kategorie, __('Seite') => $feedback->route_name ?? $feedback->url]),
            sections: [['title' => __('Text'), 'text' => $feedback->text]],
            actionLabel: __('Meldung ansehen'),
            actionUrl: route('admin.feedback.index'),
            digestTitle: __('Neue Meldung («:kategorie») von :name', ['kategorie' => $kategorie, 'name' => $name]),
        );
    }
}
