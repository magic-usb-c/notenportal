<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Services\Notifications\MailContent;
use App\Support\NotenSkala;

/** Ein Fach-, Modul- oder Semesterschnitt ist neu unter die Grenze «genügend» gefallen. */
final class BelowThreshold
{
    /** @param  list<array{label: string, alt: ?float, neu: float}>  $unterschritten */
    public static function content(Lernender $lernender, array $unterschritten, bool $fuerBetreuer): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);
        $rows = array_map(fn (array $u) => [
            $u['label'],
            $u['alt'] !== null ? NotenSkala::format($u['alt']) : '–',
            NotenSkala::format($u['neu']),
        ], $unterschritten);

        $anzahl = count($unterschritten);
        $betreff = $fuerBetreuer
            ? ($anzahl === 1 ? $unterschritten[0]['label'].' von '.$name.' ist ungenügend' : $name.': '.$anzahl.' Schnitte neu ungenügend')
            : ($anzahl === 1 ? $unterschritten[0]['label'].' ist neu ungenügend' : $anzahl.' Schnitte sind neu ungenügend');

        return new MailContent(
            subject: $betreff,
            lines: $fuerBetreuer
                ? ["Bei {$name} ist ".($anzahl === 1 ? 'ein Schnitt' : 'ein Schnitt bzw. mehrere Schnitte')." unter die Grenze «genügend» gefallen."]
                : ['Nach der letzten Note liegt folgender Schnitt unter der Grenze «genügend»:'],
            table: ['head' => ['Bereich', 'Vorher', 'Neu'], 'rows' => $rows],
            actionLabel: 'Noten ansehen',
            actionUrl: $fuerBetreuer
                ? route('trainer.learners.show', $lernender->lernender_id)
                : route('learner.grades.index'),
            digestTitle: $betreff,
        );
    }
}
