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
            ? ($anzahl === 1 ? __(':label von :name ist ungenügend', ['label' => $unterschritten[0]['label'], 'name' => $name]) : __(':name: :anzahl Schnitte neu ungenügend', ['name' => $name, 'anzahl' => $anzahl]))
            : ($anzahl === 1 ? __(':label ist neu ungenügend', ['label' => $unterschritten[0]['label']]) : __(':anzahl Schnitte sind neu ungenügend', ['anzahl' => $anzahl]));

        return new MailContent(
            subject: $betreff,
            lines: $fuerBetreuer
                ? [$anzahl === 1
                    ? __('Bei :name ist ein Schnitt unter die Grenze «genügend» gefallen.', ['name' => $name])
                    : __('Bei :name ist ein Schnitt bzw. mehrere Schnitte unter die Grenze «genügend» gefallen.', ['name' => $name])]
                : [__('Nach der letzten Note liegt folgender Schnitt unter der Grenze «genügend»:')],
            table: ['head' => [__('Bereich'), __('Vorher'), __('Neu')], 'rows' => $rows],
            actionLabel: __('Noten ansehen'),
            actionUrl: $fuerBetreuer
                ? route('trainer.learners.show', $lernender->lernender_id)
                : route('learner.grades.index'),
            digestTitle: $betreff,
        );
    }
}
