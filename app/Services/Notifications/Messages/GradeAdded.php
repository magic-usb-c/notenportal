<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Models\Note;
use App\Services\Notifications\MailContent;

/** Ein betreuter Lernender hat (selbst oder per Import) eine oder mehrere Noten erfasst. */
final class GradeAdded
{
    use NoteText;

    public static function einzeln(Lernender $lernender, Note $note, string $zielUrl): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);
        $bezug = self::bezeichnung($note);

        return new MailContent(
            subject: 'Neue Note von '.$name.': '.$bezug,
            lines: [$name.' hat eine neue Note erfasst.'],
            facts: ['Fach / Modul' => $bezug, 'Note' => (string) $note->note_wert, 'Datum' => self::datum($note)],
            actionLabel: 'Noten ansehen',
            actionUrl: $zielUrl,
            digestTitle: 'Neue Note von '.$name.': '.$bezug.' – '.$note->note_wert,
        );
    }

    public static function sammel(Lernender $lernender, int $anzahl, string $zielUrl): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);
        $betreff = $name.': '.$anzahl.' Noten importiert';

        return new MailContent(
            subject: $betreff,
            lines: [$name.' hat '.$anzahl.' Noten importiert.'],
            actionLabel: 'Noten ansehen',
            actionUrl: $zielUrl,
            digestTitle: $betreff,
        );
    }
}
