<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Lernender;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Notifications\Messages\BelowThreshold;

/**
 * Beobachtet Element- und Semesterschnitte eines Lernenden rund um eine Notenänderung
 * (anlegen, ändern, importieren) und meldet, wenn einer davon neu unter die Grenze «genügend»
 * fällt. Controller brauchen nur: $vorher = $watcher->schnappschuss($id); … speichern …; $watcher->pruefen($id, $vorher);
 */
final class GradeWatcher
{
    public function __construct(private readonly NotenQuelle $quelle = new NotenQuelle) {}

    /** @return array<string, array{label: string, wert: float}> Schnappschuss vor der Änderung. */
    public function schnappschuss(int $lernenderId): array
    {
        return $this->werte($this->quelle->auswertung($lernenderId));
    }

    /** @param  array<string, array{label: string, wert: float}>  $vorher */
    public function pruefen(int $lernenderId, array $vorher): void
    {
        $a = $this->quelle->auswertung($lernenderId);
        $nachher = $this->werte($a);
        $grenze = $a->konfiguration->genuegend;

        $unterschritten = [];
        foreach ($nachher as $schluessel => $info) {
            $alt = $vorher[$schluessel]['wert'] ?? null;
            $neu = $info['wert'];
            if ($neu < $grenze - 1e-9 && ($alt === null || $alt >= $grenze - 1e-9)) {
                $unterschritten[] = ['label' => $info['label'], 'alt' => $alt, 'neu' => $neu];
            }
        }

        if ($unterschritten === []) {
            return;
        }

        $lernender = Lernender::with('benutzer')->find($lernenderId);
        if (! $lernender?->benutzer) {
            return;
        }

        Notifier::send($lernender->benutzer, NotificationCatalog::BELOW_THRESHOLD, fn () => BelowThreshold::content($lernender, $unterschritten, fuerBetreuer: false));

        foreach (Empfaenger::aktiveBetreuer($lernenderId) as $betreuerUser) {
            Notifier::send($betreuerUser, NotificationCatalog::BELOW_THRESHOLD, fn () => BelowThreshold::content($lernender, $unterschritten, fuerBetreuer: true));
        }
    }

    /** @return array<string, array{label: string, wert: float}> */
    private function werte(Auswertung $a): array
    {
        $out = [];
        foreach ($a->elemente as $schluessel => $e) {
            if ($e->note === null) {
                continue;
            }
            $label = $e->typ === Element::FACH
                ? $e->label.' ('.$a->konfiguration->semesterName($e->semesterId).')'
                : $e->label;
            $out[$schluessel] = ['label' => $label, 'wert' => $e->note];
        }

        foreach ($a->semesterIds() as $sid) {
            $note = $a->semester($sid)['note'];
            if ($note !== null) {
                $out['semester:'.$sid] = ['label' => 'Semesterschnitt '.$a->konfiguration->semesterName($sid), 'wert' => $note];
            }
        }

        return $out;
    }
}
