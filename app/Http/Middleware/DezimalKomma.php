<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Dezimalfelder sind Textfelder (inputmode="decimal"), damit der Browser vorbelegte Werte nicht nach seiner Sprache
 * mit Komma zeigt. Wer «4,5» tippt, meint 4.5: für die Felder unten wird ein einzelnes Dezimalkomma vor der
 * Validierung zum Punkt. Nur Eingaben, die ganz aus Ziffern mit einem Komma bestehen; Texte bleiben, wie sie sind.
 */
final class DezimalKomma extends TransformsRequest
{
    /** Letzter Teil des Feldschlüssels (auch in Arrays wie kategorien[3][gewicht_gesamt]). */
    public const array FELDER = [
        'note_wert',
        'gewichtung_prozent',
        'zielwert',
        'note_gut',
        'note_genuegend',
        'note_kritisch',
        'gewicht_gesamt',
        'promotion_min_schnitt',
        'promotion_max_minuspunkte',
        'ziel_gewicht_summe_default',
        // Einrichtung: Gewichtssumme je Modul (anderswo ist «ziel» ein Schlüssel wie «gesamt», den die Regel nie trifft)
        'ziel',
        // Notenbaum: knoten[id][gewicht|fallnote|max_minuspunkte]
        'gewicht',
        'fallnote',
        'max_minuspunkte',
    ];

    protected function transform($key, $value)
    {
        if (! is_string($value) || ! in_array(substr((string) strrchr('.'.$key, '.'), 1), self::FELDER, true)) {
            return $value;
        }

        return preg_match('/^\s*-?\d*,\d+\s*$/', $value) === 1 ? str_replace(',', '.', trim($value)) : $value;
    }
}
