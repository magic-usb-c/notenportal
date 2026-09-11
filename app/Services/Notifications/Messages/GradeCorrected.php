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
            subject: 'Note in '.$bezug.' korrigiert',
            lines: ['Deine Note vom '.self::datum($note).' in '.$bezug.' wurde korrigiert.'],
            facts: ['Fach / Modul' => $bezug, 'Bisherige Note' => $alterWert, 'Neue Note' => (string) $note->note_wert, 'Datum' => self::datum($note)],
            actionLabel: 'Note ansehen',
            actionUrl: $zielUrl,
            digestTitle: 'Note in '.$bezug.' korrigiert: '.$alterWert.' → '.$note->note_wert,
        );
    }
}
