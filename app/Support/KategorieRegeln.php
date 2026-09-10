<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validierung und Speicherwerte der Rechenregeln einer Kategorie (Rundung, Gewicht, Promotion).
 * Gemeinsam für Stammdaten und Einrichtungsassistent.
 */
final class KategorieRegeln
{
    public const array RUNDUNGEN = ['0.1', '0.25', '0.5', '1'];

    /** @return array<string, list<string>> */
    public static function regeln(string $praefix = ''): array
    {
        return [
            $praefix.'rundung_element' => ['required', 'numeric', 'in:0,0.1,0.25,0.5,1'],
            $praefix.'rundung_schnitt' => ['required', 'numeric', 'in:0,0.1,0.25,0.5,1'],
            $praefix.'gewicht_gesamt' => ['required', 'numeric', 'min:0'],
            $praefix.'promotion_min_schnitt' => ['nullable', 'numeric', 'between:1,6'],
            $praefix.'promotion_max_ungenuegend' => ['nullable', 'integer', 'between:0,20'],
            $praefix.'promotion_max_minuspunkte' => ['nullable', 'numeric', 'between:0,20'],
        ];
    }

    /** Leere Promotion-Felder werden als null gespeichert (keine Regel). */
    public static function werte(array $validiert): array
    {
        return [
            'rundung_element' => $validiert['rundung_element'],
            'rundung_schnitt' => $validiert['rundung_schnitt'],
            'gewicht_gesamt' => $validiert['gewicht_gesamt'],
            'promotion_min_schnitt' => $validiert['promotion_min_schnitt'] ?? null,
            'promotion_max_ungenuegend' => $validiert['promotion_max_ungenuegend'] ?? null,
            'promotion_max_minuspunkte' => $validiert['promotion_max_minuspunkte'] ?? null,
        ];
    }
}
