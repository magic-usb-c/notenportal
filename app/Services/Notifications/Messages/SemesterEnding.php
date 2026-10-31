<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Pruefung;
use App\Models\Semester;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Konfiguration;
use App\Services\Notifications\MailContent;

/** Semesterende naht: offene geplante Prüfungen, unvollständige Module, Erinnerung Zeugnis hochladen. */
final class SemesterEnding
{
    /**
     * @param  list<Pruefung>  $offenePruefungen
     * @param  list<Element>  $offeneModule
     */
    public static function content(Semester $semester, array $offenePruefungen, array $offeneModule, ?int $lernenderId = null): MailContent
    {
        $name = Konfiguration::ausDb()->semesterName((int) $semester->semester_id, $lernenderId);
        $sections = [];
        // Abgabetermine sind keine Prüfungen: eigene Liste unter eigener Überschrift
        $zeile = fn (Pruefung $p) => '- '.$p->bezeichnung().' ('.$p->datum->format('d.m.Y').')';
        $nurPruefungen = array_values(array_filter($offenePruefungen, fn (Pruefung $p) => ! $p->istAbgabe()));
        $nurAbgaben = array_values(array_filter($offenePruefungen, fn (Pruefung $p) => $p->istAbgabe()));
        if ($nurPruefungen !== []) {
            $sections[] = ['title' => __('Offene geplante Prüfungen'), 'text' => implode("\n", array_map($zeile, $nurPruefungen))];
        }
        if ($nurAbgaben !== []) {
            $sections[] = ['title' => __('Offene Abgaben'), 'text' => implode("\n", array_map($zeile, $nurAbgaben))];
        }
        if ($offeneModule !== []) {
            $zeilen = array_map(fn (Element $e) => '- '.$e->label.' ('.__('offen: :prozent %', ['prozent' => rtrim(rtrim(number_format((float) $e->offenGewicht(), 2, '.', ''), '0'), '.')]).')', $offeneModule);
            $sections[] = ['title' => __('Module mit offener Gewichtung'), 'text' => implode("\n", $zeilen)];
        }

        return new MailContent(
            subject: __('Semesterende naht: :semester', ['semester' => $name]),
            lines: [__('Denk daran, offene Prüfungen einzutragen und dein Zeugnis hochzuladen, sobald es da ist.')],
            facts: [__('Semester') => $name, __('Ende') => $semester->end_datum->format('d.m.Y')],
            sections: $sections,
            actionLabel: __('Zur Prüfungsliste'),
            actionUrl: route('learner.exams.index'),
            digestTitle: __('Semesterende naht: :semester', ['semester' => $name]),
        );
    }
}
