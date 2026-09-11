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
            $sections[] = ['title' => 'Offene geplante Prüfungen', 'text' => implode("\n", $zeilen)];
        }
        if ($offeneModule !== []) {
            $zeilen = array_map(fn (Element $e) => '- '.$e->label.' (offen: '.rtrim(rtrim(number_format((float) $e->offenGewicht(), 1), '0'), '.').' %)', $offeneModule);
            $sections[] = ['title' => 'Module mit offener Gewichtung', 'text' => implode("\n", $zeilen)];
        }

        return new MailContent(
            subject: 'Semesterende naht: '.$semester->bezeichnung,
            lines: ['Das Semester «'.$semester->bezeichnung.'» endet am '.$semester->end_datum->format('d.m.Y').'. Denk daran, offene Prüfungen einzutragen und dein Zeugnis hochzuladen, sobald es da ist.'],
            facts: ['Semester' => $semester->bezeichnung, 'Ende' => $semester->end_datum->format('d.m.Y')],
            sections: $sections,
            actionLabel: 'Zur Prüfungsliste',
            actionUrl: route('learner.exams.index'),
            digestTitle: 'Semesterende naht: '.$semester->bezeichnung,
        );
    }
}
