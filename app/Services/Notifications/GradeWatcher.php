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

    /** @return array<string, array{typ: string, name: string, semesterId: ?int, wert: float}> Schnappschuss vor der Änderung. */
    public function schnappschuss(int $lernenderId): array
    {
        return $this->werte($this->quelle->auswertung($lernenderId));
    }

    /** @param  array<string, array{typ: string, name: string, semesterId: ?int, wert: float}>  $vorher */
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
                $unterschritten[] = ['typ' => $info['typ'], 'name' => $info['name'], 'semesterId' => $info['semesterId'], 'alt' => $alt, 'neu' => $neu];
            }
        }

        if ($unterschritten === []) {
            return;
        }

        $lernender = Lernender::with('benutzer')->find($lernenderId);
        if (! $lernender?->benutzer) {
            return;
        }

        Notifier::send($lernender->benutzer, NotificationCatalog::BELOW_THRESHOLD, fn () => BelowThreshold::content($lernender, $this->beschriftet($a, $unterschritten), fuerBetreuer: false));

        foreach (Empfaenger::aktiveBetreuer($lernenderId) as $betreuerUser) {
            Notifier::send($betreuerUser, NotificationCatalog::BELOW_THRESHOLD, fn () => BelowThreshold::content($lernender, $this->beschriftet($a, $unterschritten), fuerBetreuer: true));
        }
    }

    /**
     * Beschriftungen erst hier bauen: die Closure läuft in der Sprache des Empfängers
     * (Notifier setzt die Locale), nicht in der des Auslösers.
     *
     * @param  list<array{typ: string, name: string, semesterId: ?int, alt: ?float, neu: float}>  $unterschritten
     * @return list<array{label: string, alt: ?float, neu: float}>
     */
    private function beschriftet(Auswertung $a, array $unterschritten): array
    {
        return array_map(function (array $u) use ($a) {
            $semester = $a->konfiguration->semesterName($u['semesterId'], $a->lernenderId);
            $label = match ($u['typ']) {
                Element::FACH => $u['name'].' ('.$semester.')',
                'semester' => __('Semesterschnitt :semester', ['semester' => $semester]),
                default => $u['name'],
            };

            return ['label' => $label, 'alt' => $u['alt'], 'neu' => $u['neu']];
        }, $unterschritten);
    }

    /** @return array<string, array{typ: string, name: string, semesterId: ?int, wert: float}> */
    private function werte(Auswertung $a): array
    {
        $out = [];
        foreach ($a->elemente as $schluessel => $e) {
            // Nicht zählende Fächer fliessen in keinen Schnitt ein (Übersicht, Bericht): keine Meldung «neu ungenügend»
            if ($e->note === null || ! $e->zaehlt) {
                continue;
            }
            $out[$schluessel] = ['typ' => $e->typ, 'name' => $e->label, 'semesterId' => $e->semesterId, 'wert' => $e->note];
        }

        foreach ($a->semesterIds() as $sid) {
            $note = $a->semester($sid)['note'];
            if ($note !== null) {
                $out['semester:'.$sid] = ['typ' => 'semester', 'name' => '', 'semesterId' => $sid, 'wert' => $note];
            }
        }

        return $out;
    }
}
