<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Pruefung;
use App\Models\Semester;
use App\Services\Auswertung\Element;
use App\Services\Notifications\MailContent;

/** Semesterende naht: offene geplante Prüfungen, unvollständige Module, Erinnerung Zeugnis hochladen. */
final class SemesterEnding
{
    /**
     * @param  list<Pruefung>  $offenePruefungen
     * @param  list<Element>  $offeneModule
     */
    public static function content(Semester $semester, array $offenePruefungen, array $offeneModule): MailContent
    {
        $sections = [];
        if ($offenePruefungen !== []) {
            $zeilen = array_map(fn (Pruefung $p) => '- '.$p->bezeichnung().' ('.$p->datum->format('d.m.Y').')', $offenePruefungen);
            $sections[] = ['title' => __('Offene geplante Prüfungen'), 'text' => implode("\n", $zeilen)];
        }
        if ($offeneModule !== []) {
            $zeilen = array_map(fn (Element $e) => '- '.$e->label.' ('.__('offen: :prozent %', ['prozent' => rtrim(rtrim(number_format((float) $e->offenGewicht(), 1), '0'), '.')]).')', $offeneModule);
            $sections[] = ['title' => __('Module mit offener Gewichtung'), 'text' => implode("\n", $zeilen)];
        }

        return new MailContent(
            subject: __('Semesterende naht: :semester', ['semester' => $semester->bezeichnung]),
            lines: [__('Das Semester «:semester» endet am :ende. Denk daran, offene Prüfungen einzutragen und dein Zeugnis hochzuladen, sobald es da ist.', ['semester' => $semester->bezeichnung, 'ende' => $semester->end_datum->format('d.m.Y')])],
            facts: [__('Semester') => $semester->bezeichnung, __('Ende') => $semester->end_datum->format('d.m.Y')],
            sections: $sections,
            actionLabel: __('Zur Prüfungsliste'),
            actionUrl: route('learner.exams.index'),
            digestTitle: __('Semesterende naht: :semester', ['semester' => $semester->bezeichnung]),
        );
    }
}
