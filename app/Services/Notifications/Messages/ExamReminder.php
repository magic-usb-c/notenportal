<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Pruefung;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\MailContent;
use App\Support\NotenSkala;
use App\Support\Zahl;

/**
 * Erinnerung an eine geplante Prüfung (bzw. einen Abgabetermin, dann ohne Prüfungsangaben): Datum/Uhrzeit, Fach/Modul, Titel, Gewichtung, bisheriger Schnitt,
 * Prüfungsart, Dauer, erlaubte Hilfsmittel, Prüfungsstoff und eigene Notizen – der Lernende soll die Mail
 * lesen und wissen, was er lernen muss, ohne das Portal öffnen zu müssen.
 */
final class ExamReminder
{
    public static function content(Pruefung $p): MailContent
    {
        $p->loadMissing(['fach', 'modul']);
        $bezug = $p->bezeichnung();
        $gewicht = $p->gewichtung_prozent !== null ? Zahl::prozent($p->gewichtung_prozent) : '–';
        $datum = $p->datum->format('d.m.Y').($p->uhrzeit ? ', '.$p->beginn()->format('H:i') : '');

        $a = (new NotenQuelle)->auswertung((int) $p->lernender_id);
        $schnitt = $p->fach_id
            ? $a->fach((int) $p->fach_id)['note']
            : ($a->elemente['m'.$p->modul_id]->note ?? null);

        // Abgabetermine behalten ihre Erinnerung, nennen sich aber nicht Prüfung und tragen keine Prüfungsangaben.
        $abgabe = $p->istAbgabe();
        $betreff = $abgabe
            ? __('Abgabe in :bezug am :datum', ['bezug' => $bezug, 'datum' => $p->datum->format('d.m.Y')])
            : __('Prüfung in :bezug am :datum', ['bezug' => $bezug, 'datum' => $p->datum->format('d.m.Y')]);

        return new MailContent(
            subject: $betreff,
            lines: [$abgabe ? __('Dein nächster Abgabetermin steht an.') : __('Deine nächste Prüfung steht an.')],
            facts: array_filter([
                __('Datum') => $datum,
                __('Fach / Modul') => $bezug,
                __('Titel') => $p->titel,
                __('Gewichtung') => $abgabe ? null : $gewicht,
                __('Prüfungsart') => $abgabe ? null : $p->pruefungsart,
                __('Dauer') => ! $abgabe && $p->dauer_minuten ? __(':minuten Minuten', ['minuten' => $p->dauer_minuten]) : null,
                __('Erlaubte Hilfsmittel') => $abgabe ? null : $p->hilfsmittel,
                __('Bisheriger Schnitt') => $schnitt !== null ? NotenSkala::format($schnitt) : null,
            ]),
            sections: array_filter([
                $p->stoff ? ['title' => __('Prüfungsstoff'), 'text' => $p->stoff] : null,
                $p->notizen ? ['title' => __('Eigene Notizen'), 'text' => $p->notizen] : null,
            ]),
            actionLabel: __('Zur Agenda'),
            actionUrl: route('learner.exams.index'),
            digestTitle: $betreff,
        );
    }
}
