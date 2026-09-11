<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Pruefung;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\MailContent;
use App\Support\NotenSkala;

/**
 * Erinnerung an eine geplante Prüfung: Datum/Uhrzeit, Fach/Modul, Titel, Gewichtung, bisheriger Schnitt,
 * Prüfungsart, Dauer, erlaubte Hilfsmittel, Prüfungsstoff und eigene Notizen – der Lernende soll die Mail
 * lesen und wissen, was er lernen muss, ohne das Portal öffnen zu müssen.
 */
final class ExamReminder
{
    public static function content(Pruefung $p): MailContent
    {
        $p->loadMissing(['fach', 'modul']);
        $bezug = $p->bezeichnung();
        $gewicht = $p->gewichtung_prozent !== null ? NotenSkala::format($p->gewichtung_prozent, 1).' %' : '–';
        $datum = $p->datum->format('d.m.Y').($p->uhrzeit ? ', '.$p->beginn()->format('H:i') : '');

        $a = (new NotenQuelle)->auswertung((int) $p->lernender_id);
        $schnitt = $p->fach_id
            ? $a->fach((int) $p->fach_id)['note']
            : ($a->elemente['m'.$p->modul_id]->note ?? null);

        return new MailContent(
            subject: __('Prüfung in :bezug am :datum', ['bezug' => $bezug, 'datum' => $p->datum->format('d.m.Y')]),
            lines: [__('Am :datum steht deine nächste Prüfung an.', ['datum' => $p->datum->format('d.m.Y')])],
            facts: array_filter([
                __('Datum') => $datum,
                __('Fach / Modul') => $bezug,
                __('Titel') => $p->titel,
                __('Gewichtung') => $gewicht,
                __('Prüfungsart') => $p->pruefungsart,
                __('Dauer') => $p->dauer_minuten ? __(':minuten Minuten', ['minuten' => $p->dauer_minuten]) : null,
                __('Erlaubte Hilfsmittel') => $p->hilfsmittel,
                __('Bisheriger Schnitt') => $schnitt !== null ? NotenSkala::format($schnitt) : null,
            ]),
            sections: array_filter([
                $p->stoff ? ['title' => __('Prüfungsstoff'), 'text' => $p->stoff] : null,
                $p->notizen ? ['title' => __('Eigene Notizen'), 'text' => $p->notizen] : null,
            ]),
            actionLabel: __('Zur Agenda'),
            actionUrl: route('learner.exams.index'),
            digestTitle: __('Prüfung in :bezug am :datum', ['bezug' => $bezug, 'datum' => $p->datum->format('d.m.Y')]),
        );
    }
}
