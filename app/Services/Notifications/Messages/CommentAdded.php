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
            subject: __('Neuer Kommentar zu :bezug', ['bezug' => $bezug]),
            lines: [__(':name hat die Note vom :datum in :bezug kommentiert:', ['name' => $name, 'datum' => self::datum($note), 'bezug' => $bezug])],
            facts: [__('Fach / Modul') => $bezug, __('Note') => $note->anzeige(), __('Datum') => self::datum($note)],
            sections: [['title' => __('Kommentar'), 'text' => $kommentarText]],
            actionLabel: __('Kommentar ansehen'),
            actionUrl: $zielUrl,
            digestTitle: __('Neuer Kommentar von :name zu :bezug', ['name' => $name, 'bezug' => $bezug]),
        );
    }
}
