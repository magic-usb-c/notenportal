<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Note;
use App\Models\User;
use App\Services\Notifications\MailContent;

/** Der Berufsbildner hat eine oder mehrere Noten als gesehen markiert. */
final class GradeSeen
{
    use NoteText;

    public static function einzeln(Note $note, string $zielUrl, ?User $betrachter = null): MailContent
    {
        $bezug = self::bezeichnung($note);
        $name = self::name($betrachter);

        return new MailContent(
            subject: __('Note in :bezug angesehen', ['bezug' => $bezug]),
            lines: [$name !== '' ? __(':name hat die Note angesehen.', ['name' => $name]) : __('Die Note wurde angesehen.')],
            facts: [__('Fach / Modul') => $bezug, __('Note') => $note->anzeige(), __('Datum') => self::datum($note)],
            actionLabel: __('Note ansehen'),
            actionUrl: $zielUrl,
            digestTitle: __('Note in :bezug angesehen', ['bezug' => $bezug]),
        );
    }

    public static function sammel(int $anzahl, string $zielUrl, ?User $betrachter = null): MailContent
    {
        $betreff = $anzahl === 1 ? __('1 Note angesehen') : __(':anzahl Noten angesehen', ['anzahl' => $anzahl]);
        $name = self::name($betrachter);
        $zeile = match (true) {
            $name !== '' && $anzahl === 1 => __(':name hat 1 Note als gesehen markiert.', ['name' => $name]),
            $name !== '' => __(':name hat :anzahl Noten als gesehen markiert.', ['name' => $name, 'anzahl' => $anzahl]),
            $anzahl === 1 => __('1 Note wurde als gesehen markiert.'),
            default => __(':anzahl Noten wurden als gesehen markiert.', ['anzahl' => $anzahl]),
        };

        return new MailContent(
            subject: $betreff,
            lines: [$zeile],
            actionLabel: __('Noten ansehen'),
            actionUrl: $zielUrl,
            digestTitle: $betreff,
        );
    }

    /** Name der Person, die markiert hat – Berufsbildner oder Admin, darum nie «Dein Berufsbildner». */
    private static function name(?User $betrachter): string
    {
        return $betrachter ? trim($betrachter->vorname.' '.$betrachter->nachname) : '';
    }
}
