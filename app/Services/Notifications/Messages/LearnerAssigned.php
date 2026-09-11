<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Services\Notifications\MailContent;

/** Ein Lernender wurde einem Berufsbildner zur Betreuung zugewiesen. */
final class LearnerAssigned
{
    public static function content(Lernender $lernender): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);
        $beruf = $lernender->lehrberuf?->name;

        return new MailContent(
            subject: __('Neue Betreuung: :name', ['name' => $name]),
            lines: [$beruf
                ? __('Du betreust ab sofort :name («:beruf»).', ['name' => $name, 'beruf' => $beruf])
                : __('Du betreust ab sofort :name.', ['name' => $name])],
            facts: array_filter([__('Lehrberuf') => $beruf, __('Lehrbeginn') => $lernender->lehrbeginn?->format('d.m.Y')]),
            actionLabel: __('Zum Cockpit'),
            actionUrl: route('trainer.learners.show', $lernender->lernender_id),
            digestTitle: __('Neue Betreuung: :name', ['name' => $name]),
        );
    }
}
