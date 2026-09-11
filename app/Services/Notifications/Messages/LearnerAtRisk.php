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
            subject: __('Lernstand kritisch: :name', ['name' => $name]),
            lines: [__('Der Lernstand von :name steht auf Rot:', ['name' => $name])],
            sections: [['title' => __('Gründe'), 'text' => implode("\n", array_map(fn ($g) => '- '.$g, $gruende))]],
            actionLabel: __('Zum Cockpit'),
            actionUrl: route('trainer.learners.show', $lernender->lernender_id),
            digestTitle: __('Lernstand kritisch: :name', ['name' => $name]),
        );
    }
}
