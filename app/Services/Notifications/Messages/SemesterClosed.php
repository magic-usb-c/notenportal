<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Models\Semester;
use App\Services\Auswertung\Auswertung;
use App\Services\Notifications\MailContent;
use App\Support\NotenSkala;

/** Semesterabschluss: Zeugnisnoten ans den Lernenden, eine Zeile je Lernendem an die aktiven Betreuer. */
final class SemesterClosed
{
    public static function lernender(Semester $semester, Auswertung $a): MailContent
    {
        $r = $a->semester($semester->semester_id);
        $rows = array_map(fn ($e) => [$e->label, $e->note !== null ? NotenSkala::format($e->note) : '–'], $r['elemente']);

        return new MailContent(
            subject: 'Semesterabschluss: '.$semester->bezeichnung,
            lines: ['Das Semester «'.$semester->bezeichnung.'» ist abgeschlossen. Hier sind deine Zeugnisnoten. Lade dein Zeugnis hoch, sobald du es hast.'],
            facts: ['Semesterschnitt' => $r['note'] !== null ? NotenSkala::format($r['note']) : '–'],
            table: $rows !== [] ? ['head' => ['Fach / Modul', 'Note'], 'rows' => $rows] : null,
            actionLabel: 'Zeugnis hochladen',
            actionUrl: route('learner.documents.index'),
            digestTitle: 'Semesterabschluss: '.$semester->bezeichnung,
        );
    }

    /** @param  list<array{lernender: Lernender, schnitt: ?float, ungenuegend: int}>  $zeilen */
    public static function betreuer(Semester $semester, array $zeilen): MailContent
    {
        $rows = array_map(fn (array $z) => [
            trim($z['lernender']->benutzer->vorname.' '.$z['lernender']->benutzer->nachname),
            $z['schnitt'] !== null ? NotenSkala::format($z['schnitt']) : '–',
            $z['ungenuegend'] > 0 ? (string) $z['ungenuegend'] : '–',
        ], $zeilen);

        return new MailContent(
            subject: 'Semesterabschluss «'.$semester->bezeichnung.'» – deine Lernenden',
            lines: ['Das Semester «'.$semester->bezeichnung.'» ist abgeschlossen. Hier der Überblick über deine Lernenden.'],
            table: ['head' => ['Lernender', 'Semesterschnitt', 'Ungenügende Elemente'], 'rows' => $rows],
            actionLabel: 'Zu den Lernenden',
            actionUrl: route('trainer.learners.index'),
            digestTitle: 'Semesterabschluss «'.$semester->bezeichnung.'» – '.count($zeilen).' Lernende',
        );
    }
}
