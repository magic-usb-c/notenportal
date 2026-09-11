<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Pruefung;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\MailContent;
use App\Support\NotenSkala;

/**
 * Erinnerung an eine geplante Prüfung. Aktuell: Datum, Fach/Modul, Titel, Gewichtung, bisheriger Schnitt.
 * Prüfungsart, Dauer, Hilfsmittel und Stoff kommen in einer späteren Erweiterung dazu.
 */
final class ExamReminder
{
    public static function content(Pruefung $p): MailContent
    {
        $p->loadMissing(['fach', 'modul']);
        $bezug = $p->bezeichnung();
        $gewicht = $p->gewichtung_prozent !== null ? NotenSkala::format($p->gewichtung_prozent, 1).' %' : '–';

        $a = (new NotenQuelle)->auswertung((int) $p->lernender_id);
        $schnitt = $p->fach_id
            ? $a->fach((int) $p->fach_id)['note']
            : ($a->elemente['m'.$p->modul_id]->note ?? null);

        return new MailContent(
            subject: 'Prüfung in '.$bezug.' am '.$p->datum->format('d.m.Y'),
            lines: ['Am '.$p->datum->format('d.m.Y').' steht deine nächste Prüfung an.'],
            facts: array_filter([
                'Datum' => $p->datum->format('d.m.Y'),
                'Fach / Modul' => $bezug,
                'Titel' => $p->titel,
                'Gewichtung' => $gewicht,
                'Bisheriger Schnitt' => $schnitt !== null ? NotenSkala::format($schnitt) : null,
            ]),
            actionLabel: 'Zur Prüfungsliste',
            actionUrl: route('learner.exams.index'),
            digestTitle: 'Prüfung in '.$bezug.' am '.$p->datum->format('d.m.Y'),
        );
    }
}
