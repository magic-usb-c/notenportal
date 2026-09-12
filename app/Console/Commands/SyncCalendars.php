<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CalendarFeed;
use App\Services\Calendar\CalendarSync;
use Illuminate\Console\Command;

/** Stündlicher Abgleich aller abonnierten Kalender; ein fehlerhafter Kalender hält die anderen nicht auf. */
class SyncCalendars extends Command
{
    protected $signature = 'calendar:sync {--feed= : nur diesen Kalender (ID)}';

    protected $description = 'Abonnierte iCal-Kalender abgleichen (Termine, Lektionen, Prüfungen)';

    public function handle(CalendarSync $sync): int
    {
        $feeds = CalendarFeed::query()->when($this->option('feed'), fn ($q, $id) => $q->whereKey((int) $id))
            ->whereHas('lernender', fn ($q) => $q->whereNull('geloescht_am'))->get();
        $fehler = 0;

        foreach ($feeds as $feed) {
            try {
                $s = $sync->sync($feed);
                // Bei 304 stammen die Zahlen aus dem letzten echten Lauf; sie als frisch abgeglichen
                // auszugeben, läse sich wie «0 Prüfungen», also wie ein Verlust.
                $this->line($s['unchanged']
                    ? "#{$feed->id} {$feed->host()}: unverändert (304), Stand {$s['events']} Termine"
                    : "#{$feed->id} {$feed->host()}: {$s['events']} Termine, {$s['exams']} Prüfungen, {$s['unmatched']} ohne Zuordnung, {$s['removed']} entfernt");
            } catch (\Throwable $e) {
                $fehler++;
                $this->warn("#{$feed->id} {$feed->host()}: ".CalendarSync::fehlertext($e));
            }
        }

        return $fehler > 0 && $fehler === $feeds->count() ? self::FAILURE : self::SUCCESS;
    }
}
