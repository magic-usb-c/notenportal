<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Note;

/** Gemeinsame Textbausteine für Noten in Benachrichtigungen. */
trait NoteText
{
    /** Fach oder Modul (Nummer + Titel) einer Note. */
    private static function bezeichnung(Note $note): string
    {
        if ($note->fach_id) {
            return (string) $note->fach?->name;
        }

        $modul = $note->modulBelegung?->modul;

        return trim(($modul?->modul_nummer ?? '').' '.($modul?->titel ?? ''));
    }

    private static function datum(Note $note): string
    {
        return $note->pruefungsdatum?->format('d.m.Y') ?? '–';
    }
}
