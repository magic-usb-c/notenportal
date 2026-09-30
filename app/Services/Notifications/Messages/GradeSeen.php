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
            subject: __('Note in :bezug angesehen', ['bezug' => $bezug]),
            lines: [__('Dein Berufsbildner hat die Note vom :datum in :bezug angesehen.', ['datum' => self::datum($note), 'bezug' => $bezug])],
            facts: [__('Fach / Modul') => $bezug, __('Note') => $note->anzeige(), __('Datum') => self::datum($note)],
            actionLabel: __('Note ansehen'),
            actionUrl: $zielUrl,
            digestTitle: __('Note in :bezug angesehen', ['bezug' => $bezug]),
        );
    }

    public static function sammel(int $anzahl, string $zielUrl): MailContent
    {
        $betreff = __(':anzahl Noten angesehen', ['anzahl' => $anzahl]);

        return new MailContent(
            subject: $betreff,
            lines: [__('Dein Berufsbildner hat :anzahl Noten als gesehen markiert.', ['anzahl' => $anzahl])],
            actionLabel: __('Noten ansehen'),
            actionUrl: $zielUrl,
            digestTitle: $betreff,
        );
    }
}
