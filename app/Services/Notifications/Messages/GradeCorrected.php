<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Note;
use App\Services\Notifications\MailContent;

/** Berufsbildner oder Admin hat eine Note eines Lernenden korrigiert. */
final class GradeCorrected
{
    use NoteText;

    public static function content(Note $note, string $alterWert, string $zielUrl): MailContent
    {
        $bezug = self::bezeichnung($note);

        return new MailContent(
            subject: __('Note in :bezug korrigiert', ['bezug' => $bezug]),
            lines: [__('Deine Note vom :datum in :bezug wurde korrigiert.', ['datum' => self::datum($note), 'bezug' => $bezug])],
            facts: [__('Fach / Modul') => $bezug, __('Bisherige Note') => $alterWert, __('Neue Note') => $note->anzeige(), __('Datum') => self::datum($note)],
            actionLabel: __('Note ansehen'),
            actionUrl: $zielUrl,
            digestTitle: __('Note in :bezug korrigiert: :alt → :neu', ['bezug' => $bezug, 'alt' => $alterWert, 'neu' => $note->anzeige()]),
        );
    }
}
