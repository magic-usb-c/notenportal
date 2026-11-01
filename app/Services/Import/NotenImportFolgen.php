<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Lernender;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\GradeWatcher;
use App\Services\Notifications\Messages\GradeAdded;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Protokoll;

/**
 * Nachbearbeitung einer Übernahme von Noten aus einer Datei (Notenimport, Zeugnis-Abgleich):
 * «Unter Grenzwert»-Meldung über den GradeWatcher, Sammelmeldung an die aktiven Betreuer bei eigener Übernahme
 * des Lernenden, Protokolleintrag bei Übernahme durch die Verwaltung.
 *
 * Ablauf: $vorher = schnappschuss($id); … importieren …; nachDemImport($lernender, $bereich, $neu, $vorher).
 */
final class NotenImportFolgen
{
    public function __construct(private readonly GradeWatcher $gradeWatcher = new GradeWatcher) {}

    /** @return array<string, array{typ: string, name: string, semesterId: ?int, wert: float}> */
    public function schnappschuss(int $lernenderId): array
    {
        return $this->gradeWatcher->schnappschuss($lernenderId);
    }

    /**
     * @param  ?string  $bereich  null = der Lernende selbst, sonst «admin» oder «trainer»
     * @param  array<string, array{typ: string, name: string, semesterId: ?int, wert: float}>  $vorher
     */
    public function nachDemImport(Lernender $lernender, ?string $bereich, int $neu, array $vorher): void
    {
        $this->gradeWatcher->pruefen((int) $lernender->lernender_id, $vorher);

        if ($bereich !== null) {
            Protokoll::schreiben(Protokoll::ADMIN_NOTENIMPORT_UEBERNOMMEN, $lernender, ['anzahl' => $neu]);

            return;
        }

        $lernender->loadMissing('benutzer');
        $zielUrl = route('trainer.learners.grades.index', $lernender->lernender_id);
        foreach (Empfaenger::aktiveBetreuer((int) $lernender->lernender_id) as $betreuer) {
            Notifier::send($betreuer, NotificationCatalog::GRADE_ADDED, fn () => GradeAdded::sammel($lernender, $neu, $zielUrl));
        }
    }
}
