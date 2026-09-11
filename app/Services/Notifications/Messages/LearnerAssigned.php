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
            subject: 'Neue Betreuung: '.$name,
            lines: ['Du betreust ab sofort '.$name.($beruf ? ' («'.$beruf.'»)' : '').'.'],
            facts: array_filter(['Lehrberuf' => $beruf, 'Lehrbeginn' => $lernender->lehrbeginn?->format('d.m.Y')]),
            actionLabel: 'Zum Cockpit',
            actionUrl: route('berufsbildner.lernende.show', $lernender->lernender_id),
            digestTitle: 'Neue Betreuung: '.$name,
        );
    }
}
