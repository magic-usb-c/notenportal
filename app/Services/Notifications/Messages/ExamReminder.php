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
            subject: 'Prüfung in '.$bezug.' am '.$p->datum->format('d.m.Y'),
            lines: ['Am '.$p->datum->format('d.m.Y').' steht deine nächste Prüfung an.'],
            facts: array_filter([
                'Datum' => $datum,
                'Fach / Modul' => $bezug,
                'Titel' => $p->titel,
                'Gewichtung' => $gewicht,
                'Prüfungsart' => $p->pruefungsart,
                'Dauer' => $p->dauer_minuten ? $p->dauer_minuten.' Minuten' : null,
                'Erlaubte Hilfsmittel' => $p->hilfsmittel,
                'Bisheriger Schnitt' => $schnitt !== null ? NotenSkala::format($schnitt) : null,
            ]),
            sections: array_filter([
                $p->stoff ? ['title' => 'Prüfungsstoff', 'text' => $p->stoff] : null,
                $p->notizen ? ['title' => 'Eigene Notizen', 'text' => $p->notizen] : null,
            ]),
            actionLabel: 'Zur Agenda',
            actionUrl: route('learner.exams.index'),
            digestTitle: 'Prüfung in '.$bezug.' am '.$p->datum->format('d.m.Y'),
        );
    }
}
