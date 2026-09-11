<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Note;
use App\Models\User;
use App\Services\Notifications\MailContent;

/** Jemand hat eine Note kommentiert – geht an die Gegenseite (nie an den Autor selbst). */
final class CommentAdded
{
    use NoteText;

    public static function content(Note $note, string $kommentarText, User $autor, string $zielUrl): MailContent
    {
        $name = trim($autor->vorname.' '.$autor->nachname);
        $bezug = self::bezeichnung($note);

        return new MailContent(
            subject: 'Neuer Kommentar zu '.$bezug,
            lines: [$name.' hat die Note vom '.self::datum($note).' in '.$bezug.' kommentiert:'],
            facts: ['Fach / Modul' => $bezug, 'Note' => (string) $note->note_wert, 'Datum' => self::datum($note)],
            sections: [['title' => 'Kommentar', 'text' => $kommentarText]],
            actionLabel: 'Kommentar ansehen',
            actionUrl: $zielUrl,
            digestTitle: 'Neuer Kommentar von '.$name.' zu '.$bezug,
        );
    }
}
