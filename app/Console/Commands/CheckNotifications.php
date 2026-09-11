<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lernender;
use App\Models\NotificationMark;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Lernstand;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\Messages\ExamReminder;
use App\Services\Notifications\Messages\Inactivity;
use App\Services\Notifications\Messages\LearnerAtRisk;
use App\Services\Notifications\Messages\SemesterClosed;
use App\Services\Notifications\Messages\SemesterEnding;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tägliche Prüfung (Schedule::command in routes/console.php, 06:30): Prüfungserinnerung, Inaktivität,
 * Semesterende/-abschluss, Lernstand kritisch. Nie gegen Prod ausführen ausser mit --dry-run.
 */
#[Description('Tägliche Benachrichtigungsprüfung: Prüfungen, Inaktivität, Semester, Lernstand')]
#[Signature('notifications:check {--dry-run : Nur ausgeben, wer was bekäme, ohne zu versenden}')]
class CheckNotifications extends Command
{
    private bool $dryRun = false;

    public function handle(NotenQuelle $quelle, LernstandRechner $lernstaende): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $this->examReminder();
        $this->inactivity();
        $this->semesterEnding($quelle);
        $this->semesterClosed($quelle);
        $this->learnerAtRisk($lernstaende);

        return self::SUCCESS;
    }

    /** @return Collection<int, Lernender> aktive Lernende mit laufender Lehre */
    private function aktiveLernende(): Collection
    {
        $heute = today()->toDateString();

        return Lernender::query()
            ->whereHas('benutzer', fn ($q) => $q->where('aktiv', true))
            ->where('lehrbeginn', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('lehrende')->orWhere('lehrende', '>=', $heute))
            ->with('benutzer')
            ->get();
    }

    private function melden(User $empfaenger, string $type, string $key, MailContent $inhalt, string $log): void
    {
        if ($this->dryRun) {
            $this->line('[dry-run] '.$log.' → '.$empfaenger->email);

            return;
        }

        if (Notifier::once($empfaenger, $type, $key)) {
            Notifier::send($empfaenger, $type, $inhalt);
        }
    }

    private function examReminder(): void
    {
        $tage = NotificationCatalog::param(NotificationCatalog::EXAM_REMINDER, 'days_before');
        $bis = today()->addDays($tage)->toDateString();
        $heute = today()->toDateString();

        $pruefungen = Pruefung::query()
            ->offen()
            ->with(['lernender.benutzer', 'fach', 'modul'])
            ->whereDate('datum', '>=', $heute)
            ->whereDate('datum', '<=', $bis)
            ->get();

        foreach ($pruefungen as $p) {
            $benutzer = $p->lernender?->benutzer;
            if (! $benutzer?->aktiv) {
                continue;
            }

            $this->melden(
                $benutzer, NotificationCatalog::EXAM_REMINDER, 'pruefung:'.$p->pruefung_id,
                ExamReminder::content($p), 'Prüfung '.$p->bezeichnung().' am '.$p->datum->format('d.m.Y')
            );
        }
    }

    private function inactivity(): void
    {
        $tage = NotificationCatalog::param(NotificationCatalog::INACTIVITY, 'days');
        $wiederholung = NotificationCatalog::param(NotificationCatalog::INACTIVITY, 'repeat_days');
        $lernende = $this->aktiveLernende();
        if ($lernende->isEmpty()) {
            return;
        }

        $letzte = DB::table('noten')
            ->whereIn('lernender_id', $lernende->pluck('lernender_id'))
            ->whereNull('geloescht_am')
            ->groupBy('lernender_id')
            ->selectRaw('lernender_id, MAX(erstellt_am) as letzte')
            ->pluck('letzte', 'lernender_id');

        foreach ($lernende as $l) {
            $bezug = isset($letzte[$l->lernender_id]) ? Carbon::parse($letzte[$l->lernender_id]) : $l->lehrbeginn;
            if (! $bezug) {
                continue;
            }

            $tageSeit = (int) $bezug->copy()->startOfDay()->diffInDays(today());
            $ueberschuss = $tageSeit - $tage;
            if ($ueberschuss < 0) {
                continue;
            }

            $periode = intdiv($ueberschuss, $wiederholung);

            $this->melden(
                $l->benutzer, NotificationCatalog::INACTIVITY, 'inaktiv:'.$l->lernender_id.':'.$periode,
                Inactivity::content($l, $tageSeit), 'Inaktivität '.$tageSeit.' Tage ('.$l->benutzer->email.')'
            );
        }
    }

    private function semesterEnding(NotenQuelle $quelle): void
    {
        $tage = NotificationCatalog::param(NotificationCatalog::SEMESTER_ENDING, 'days_before');
        $ziel = today()->addDays($tage)->toDateString();

        $semester = Semester::query()->whereDate('end_datum', $ziel)->get();
        if ($semester->isEmpty()) {
            return;
        }

        foreach ($semester as $semesterEintrag) {
            foreach ($this->aktiveLernende() as $l) {
                if ($l->lehrbeginn->toDateString() > $semesterEintrag->end_datum->toDateString()) {
                    continue;
                }

                $offenePruefungen = $l->pruefungen()
                    ->offen()
                    ->whereBetween('datum', [$semesterEintrag->start_datum->toDateString(), $semesterEintrag->end_datum->toDateString()])
                    ->with(['fach', 'modul'])
                    ->get()
                    ->all();

                $a = $quelle->auswertung((int) $l->lernender_id);
                $offeneModule = array_values(array_filter(
                    $a->elemente,
                    fn (Element $e) => $e->typ === Element::MODUL && $e->offenGewicht() !== null && $e->offenGewicht() > 0.01
                ));

                if ($offenePruefungen === [] && $offeneModule === []) {
                    continue;
                }

                $this->melden(
                    $l->benutzer, NotificationCatalog::SEMESTER_ENDING, 'semester_ending:'.$semesterEintrag->semester_id.':'.$l->lernender_id,
                    SemesterEnding::content($semesterEintrag, $offenePruefungen, $offeneModule),
                    'Semesterende '.$semesterEintrag->bezeichnung.' ('.$l->benutzer->email.')'
                );
            }
        }
    }

    private function semesterClosed(NotenQuelle $quelle): void
    {
        $von = today()->subDays(7)->toDateString();
        $bis = today()->subDay()->toDateString();

        $semester = Semester::query()->whereBetween('end_datum', [$von, $bis])->get();
        if ($semester->isEmpty()) {
            return;
        }

        foreach ($semester as $semesterEintrag) {
            $betreuerZeilen = []; // berufsbildner_id => list<array{lernender: Lernender, schnitt: ?float, ungenuegend: int}>

            foreach ($this->aktiveLernende() as $l) {
                if ($l->lehrbeginn->toDateString() > $semesterEintrag->end_datum->toDateString()) {
                    continue;
                }

                $a = $quelle->auswertung((int) $l->lernender_id);
                $r = $a->semester((int) $semesterEintrag->semester_id);
                if ($r['elemente'] === []) {
                    continue;
                }

                $this->melden(
                    $l->benutzer, NotificationCatalog::SEMESTER_CLOSED, 'semester:'.$semesterEintrag->semester_id,
                    SemesterClosed::lernender($semesterEintrag, $a),
                    'Semesterabschluss '.$semesterEintrag->bezeichnung.' ('.$l->benutzer->email.')'
                );

                $ungenuegend = count(array_filter($r['elemente'], fn ($e) => $e->note < $a->konfiguration->genuegend - 1e-9));
                foreach (Empfaenger::aktiveBetreuer((int) $l->lernender_id) as $betreuerUser) {
                    $betreuerZeilen[$betreuerUser->benutzer_id]['user'] = $betreuerUser;
                    $betreuerZeilen[$betreuerUser->benutzer_id]['zeilen'][] = [
                        'lernender' => $l, 'schnitt' => $r['note'], 'ungenuegend' => $ungenuegend,
                    ];
                }
            }

            foreach ($betreuerZeilen as $eintrag) {
                $this->melden(
                    $eintrag['user'], NotificationCatalog::SEMESTER_CLOSED, 'semester:'.$semesterEintrag->semester_id,
                    SemesterClosed::betreuer($semesterEintrag, $eintrag['zeilen']),
                    'Semesterabschluss '.$semesterEintrag->bezeichnung.' – Betreuer ('.$eintrag['user']->email.')'
                );
            }
        }
    }

    private function learnerAtRisk(LernstandRechner $lernstaende): void
    {
        $lernende = $this->aktiveLernende();
        if ($lernende->isEmpty()) {
            return;
        }

        $ids = $lernende->pluck('lernender_id')->map(fn ($id) => (int) $id)->all();
        $stande = $lernstaende->fuer($ids);

        foreach ($lernende as $l) {
            /** @var Lernstand $stand */
            $stand = $stande[$l->lernender_id];
            $schluessel = 'rot:'.$l->lernender_id;

            if ($stand->status !== Lernstand::ROT) {
                if (! $this->dryRun) {
                    NotificationMark::where('type', NotificationCatalog::LEARNER_AT_RISK)->where('subject_key', $schluessel)->delete();
                }

                continue;
            }

            foreach (Empfaenger::aktiveBetreuer((int) $l->lernender_id) as $betreuerUser) {
                $this->melden(
                    $betreuerUser, NotificationCatalog::LEARNER_AT_RISK, $schluessel,
                    LearnerAtRisk::content($l, $stand->gruende),
                    'Lernstand kritisch '.$l->benutzer->email.' ('.$betreuerUser->email.')'
                );
            }
        }
    }
}
