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
        $name = $a->konfiguration->semesterName((int) $semester->semester_id, $a->lernenderId);

        return new MailContent(
            subject: __('Semesterabschluss: :semester', ['semester' => $name]),
            lines: [__('Das Semester «:semester» ist abgeschlossen. Hier sind deine Zeugnisnoten. Lade dein Zeugnis hoch, sobald du es hast.', ['semester' => $name])],
            facts: [__('Semesterschnitt') => $r['note'] !== null ? NotenSkala::format($r['note']) : '–'],
            table: $rows !== [] ? ['head' => [__('Fach / Modul'), __('Note')], 'rows' => $rows] : null,
            actionLabel: __('Zeugnis hochladen'),
            actionUrl: route('learner.documents.index'),
            digestTitle: __('Semesterabschluss: :semester', ['semester' => $name]),
        );
    }

    /** @param  list<array{lernender: Lernender, schnitt: ?float, ungenuegend: int}>  $zeilen */
    public static function betreuer(Semester $semester, array $zeilen): MailContent
    {
        // Eine Mail über mehrere Lernende gleichzeitig -> neutraler Name statt Rohcode
        // (dieselbe Semesterzeile hätte je nach Lehrbeginn pro Lernendem eine andere Nummer).
        $name = Semester::neutralerName($semester->start_datum) ?? $semester->bezeichnung;

        $rows = array_map(fn (array $z) => [
            trim($z['lernender']->benutzer->vorname.' '.$z['lernender']->benutzer->nachname),
            $z['schnitt'] !== null ? NotenSkala::format($z['schnitt']) : '–',
            $z['ungenuegend'] > 0 ? (string) $z['ungenuegend'] : '–',
        ], $zeilen);

        return new MailContent(
            subject: __('Semesterabschluss «:semester» – deine Lernenden', ['semester' => $name]),
            lines: [__('Das Semester «:semester» ist abgeschlossen. Hier der Überblick über deine Lernenden.', ['semester' => $name])],
            table: ['head' => [__('Lernender'), __('Semesterschnitt'), __('Ungenügende Elemente')], 'rows' => $rows],
            actionLabel: __('Zu den Lernenden'),
            actionUrl: route('trainer.learners.index'),
            digestTitle: __('Semesterabschluss «:semester» – :anzahl Lernende', ['semester' => $name, 'anzahl' => count($zeilen)]),
        );
    }
}
