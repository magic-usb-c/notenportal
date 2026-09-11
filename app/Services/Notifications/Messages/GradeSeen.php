<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Note;
use App\Services\Notifications\MailContent;

/** Der Berufsbildner hat eine oder mehrere Noten als gesehen markiert. */
final class GradeSeen
{
    use NoteText;

    public static function einzeln(Note $note, string $zielUrl): MailContent
    {
        $bezug = self::bezeichnung($note);

        return new MailContent(
            subject: 'Note in '.$bezug.' angesehen',
            lines: ['Dein Berufsbildner hat die Note vom '.self::datum($note).' in '.$bezug.' angesehen.'],
            facts: ['Fach / Modul' => $bezug, 'Note' => (string) $note->note_wert, 'Datum' => self::datum($note)],
            actionLabel: 'Note ansehen',
            actionUrl: $zielUrl,
            digestTitle: 'Note in '.$bezug.' angesehen',
        );
    }

    public static function sammel(int $anzahl, string $zielUrl): MailContent
    {
        $betreff = $anzahl.' Noten angesehen';

        return new MailContent(
            subject: $betreff,
            lines: ['Dein Berufsbildner hat '.$anzahl.' Noten als gesehen markiert.'],
            actionLabel: 'Noten ansehen',
            actionUrl: $zielUrl,
            digestTitle: $betreff,
        );
    }
}
