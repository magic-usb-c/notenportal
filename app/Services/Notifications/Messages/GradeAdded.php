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
            subject: __('Neue Note von :name: :bezug', ['name' => $name, 'bezug' => $bezug]),
            lines: [__(':name hat eine neue Note erfasst.', ['name' => $name])],
            facts: [__('Fach / Modul') => $bezug, __('Note') => $note->anzeige(), __('Datum') => self::datum($note)],
            actionLabel: __('Noten ansehen'),
            actionUrl: $zielUrl,
            digestTitle: __('Neue Note von :name: :bezug – :note', ['name' => $name, 'bezug' => $bezug, 'note' => $note->anzeige()]),
        );
    }

    public static function sammel(Lernender $lernender, int $anzahl, string $zielUrl): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);
        $betreff = __(':name: :anzahl Noten importiert', ['name' => $name, 'anzahl' => $anzahl]);

        return new MailContent(
            subject: $betreff,
            lines: [__(':name hat :anzahl Noten importiert.', ['name' => $name, 'anzahl' => $anzahl])],
            actionLabel: __('Noten ansehen'),
            actionUrl: $zielUrl,
            digestTitle: $betreff,
        );
    }
}
