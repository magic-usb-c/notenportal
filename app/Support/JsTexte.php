<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte, die resources/js/* über np.t(schluessel) holt. Das Layout gibt sie als window.npI18n aus
 * (nur ausserhalb von Deutsch, dort ist der Schlüssel der Text). Der Test
 * tests/Feature/I18n/SchluesselTest.php hält die Liste mit den np.t-Aufrufen gleich.
 */
final class JsTexte
{
    public const array SCHLUESSEL = [
        'Anzahl',
        'Berechnung fehlgeschlagen.',
        'Bitte Eingaben prüfen.',
        'Danke, deine Meldung ist eingegangen.',
        'Die Anhänge sind zusammen zu gross. Bitte weniger oder kleinere Dateien wählen.',
        'Ergebnis',
        'Gespeichert.',
        'Ergebnis: :wert',
        'Gerade viele Meldungen unterwegs – bitte kurz warten.',
        'Meldung konnte nicht gesendet werden.',
        'Sitzung abgelaufen. Seite bitte neu laden.',
        'Note',
        'Note in offenen Prüfungen: :wert',
        'Screenshot wird erstellt…',
        'Senden',
        'Senden…',
        'Tabelle',
        'Änderung konnte nicht gespeichert werden.',
        'Ziel :wert',
        'Zu viele Anfragen – bitte kurz warten.',
        'genügend :wert',
    ];

    /** @return array<string, string> Schlüssel => Übersetzung in der aktuellen Sprache */
    public static function uebersetzt(): array
    {
        return collect(self::SCHLUESSEL)->mapWithKeys(fn (string $s) => [$s => __($s)])->all();
    }
}
