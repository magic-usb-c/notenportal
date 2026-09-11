<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Services\Notifications\MailContent;

/** Die Lernstand-Ampel eines betreuten Lernenden ist auf Rot gesprungen. */
final class LearnerAtRisk
{
    /** @param  list<string>  $gruende */
    public static function content(Lernender $lernender, array $gruende): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);

        return new MailContent(
            subject: 'Lernstand kritisch: '.$name,
            lines: ['Der Lernstand von '.$name.' steht auf Rot:'],
            sections: [['title' => 'Gründe', 'text' => implode("\n", array_map(fn ($g) => '- '.$g, $gruende))]],
            actionLabel: 'Zum Cockpit',
            actionUrl: route('berufsbildner.lernende.show', $lernender->lernender_id),
            digestTitle: 'Lernstand kritisch: '.$name,
        );
    }
}
