<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\Lernender;
use App\Models\Pruefung;
use App\Models\User;
use App\Support\Einstellungen;
use App\Support\NotenSkala;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;

/**
 * iCal-Export zum Abonnieren: Lernende erhalten eigene Prüfungen und importierte Termine,
 * Berufsbildner/Admins die Prüfungen ihrer sichtbaren Lernenden. Zugriff über ein geheimes Token je Benutzer.
 */
final class CalendarExport
{
    private const int DAYS_BACK = 60;

    /**
     * Epoche für SEQUENCE (01.01.2026 00:00 UTC): monoton steigend bei jeder Änderung, aber klein
     * genug, dass kein Kalenderprogramm (32-Bit-Int) darüber hinausläuft.
     */
    private const int SEQUENCE_EPOCH = 1767225600;

    public static function token(User $user): string
    {
        if (! $user->kalender_token) {
            $user->forceFill(['kalender_token' => Str::random(48)])->save();
        }

        return (string) $user->kalender_token;
    }

    public static function resetToken(User $user): string
    {
        $user->forceFill(['kalender_token' => Str::random(48)])->save();

        return (string) $user->kalender_token;
    }

    public function forUser(User $user): string
    {
        $vorherigeLocale = App::getLocale();
        App::setLocale($user->preferredLocale());
        try {
            return $this->kalenderFuer($user);
        } finally {
            App::setLocale($vorherigeLocale);
        }
    }

    private function kalenderFuer(User $user): string
    {
        $betrieb = (string) Einstellungen::get(Einstellungen::BETRIEB_NAME, '');
        $kalender = new VCalendar([
            'PRODID' => '-//Notenportal//Agenda//DE',
            'X-WR-CALNAME' => 'Notenportal'.($betrieb !== '' ? ' '.$betrieb : '').' – '.__('Prüfungen'),
            'X-WR-TIMEZONE' => (string) config('app.timezone'),
            'X-PUBLISHED-TTL' => 'PT1H',
            'REFRESH-INTERVAL' => 'PT1H',
        ]);
        $kalender->{'REFRESH-INTERVAL'}['VALUE'] = 'DURATION';

        $lernender = $user->lernender;
        $ids = $lernender ? [(int) $lernender->lernender_id] : Lernender::sichtbarFuer($user)->pluck('lernender_id')->map(fn ($id) => (int) $id)->all();
        $mitName = $lernender === null;
        // Einmal pro Abo bestimmen: hasRole() fragt die DB bei jedem Aufruf ab.
        $cockpitRoute = $mitName ? ($user->hasRole('Admin') ? 'admin.learners.show' : 'trainer.learners.show') : null;

        Pruefung::query()->whereIn('lernender_id', $ids ?: [0])
            ->whereDate('datum', '>=', now()->subDays(self::DAYS_BACK)->toDateString())
            ->with(['fach', 'modul', 'lernender.benutzer', 'note'])->orderBy('datum')->get()
            ->each(fn (Pruefung $p) => $this->pruefung($kalender, $p, $mitName, $cockpitRoute));

        if ($lernender) {
            CalendarEvent::query()->where('lernender_id', $lernender->lernender_id)->where('kind', CalendarEvent::APPOINTMENT)
                ->where('starts_at', '>=', now()->subDays(self::DAYS_BACK))->orderBy('starts_at')->get()
                ->each(fn (CalendarEvent $e) => $this->termin($kalender, $e));
        }

        return $kalender->serialize();
    }

    /** URL im Termin: eigene Agenda (Lernender) bzw. Cockpit des Lernenden (Berufsbildner/Admin). */
    private function pruefungUrl(Pruefung $p, ?string $cockpitRoute): string
    {
        return $cockpitRoute === null
            ? route('learner.exams.index')
            : route($cockpitRoute, ['lernender_id' => $p->lernender_id]);
    }

    /** In UTC, wie von iCal-Clients erwartet (siehe DTSTAMP oben). */
    private function utc(CarbonInterface $zeit): DateTimeImmutable
    {
        return (clone $zeit)->utc()->toDateTimeImmutable();
    }

    private function pruefung(VCalendar $kalender, Pruefung $p, bool $mitName, ?string $cockpitRoute): void
    {
        $name = $mitName ? ($p->lernender?->benutzer?->vorname.' '.$p->lernender?->benutzer?->nachname).': ' : '';
        $titel = trim($p->bezeichnung().($p->titel ? ' – '.$p->titel : ''));
        $zeilen = array_filter([
            $p->pruefungsart ? __('Prüfungsart').': '.$p->pruefungsart : null,
            $p->dauer_minuten ? __('Dauer').': '.__(':minuten Minuten', ['minuten' => $p->dauer_minuten]) : null,
            $p->hilfsmittel ? __('Erlaubte Hilfsmittel').': '.$p->hilfsmittel : null,
            __('Gewichtung').': '.rtrim(rtrim(number_format((float) $p->gewichtung_prozent, 2, '.', ''), '0'), '.').' %',
            $p->note ? __('Note').': '.($p->note->note_wert !== null
                ? rtrim(rtrim(number_format((float) $p->note->note_wert, 2, '.', ''), '0'), '.')
                : NotenSkala::stufeText($p->note->note_stufe)) : null,
            $p->stoff ? "\n".__('Prüfungsstoff').":\n".$p->stoff : null,
        ]);

        $event = $kalender->add('VEVENT', [
            'UID' => 'pruefung-'.$p->pruefung_id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'SUMMARY' => $name.__('Prüfung').' '.$titel,
            'DTSTAMP' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            'DESCRIPTION' => implode("\n", $zeilen),
            'CATEGORIES' => __('Prüfung'),
            'URL' => $this->pruefungUrl($p, $cockpitRoute),
            'LAST-MODIFIED' => $this->utc($p->aktualisiert_am),
            'SEQUENCE' => max(0, $p->aktualisiert_am->getTimestamp() - self::SEQUENCE_EPOCH),
        ]);
        if ($p->raum) {
            $event->add('LOCATION', $p->raum);
        }
        if ($p->abgesagt_am) {
            $event->add('STATUS', 'CANCELLED');
        }
        $this->zeit($event, $p->beginn()->toDateTimeImmutable(), $p->uhrzeit !== null, $p->dauer_minuten ?: 60);
    }

    private function termin(VCalendar $kalender, CalendarEvent $e): void
    {
        $event = $kalender->add('VEVENT', [
            'UID' => 'termin-'.$e->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'SUMMARY' => $e->summary,
            'DTSTAMP' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            'URL' => route('learner.exams.index'),
            'LAST-MODIFIED' => $this->utc($e->updated_at),
            'SEQUENCE' => max(0, $e->updated_at->getTimestamp() - self::SEQUENCE_EPOCH),
        ]);
        if ($e->description) {
            $event->add('DESCRIPTION', $e->description);
        }
        if ($e->location) {
            $event->add('LOCATION', $e->location);
        }
        $start = $e->starts_at->toDateTimeImmutable();
        $minuten = $e->ends_at ? max(1, (int) $e->starts_at->diffInMinutes($e->ends_at)) : 60;
        // Mehrtägige Ganztagestermine: ends_at ist das exklusive DTEND des Quellkalenders.
        $ende = $e->all_day && $e->ends_at && $e->ends_at->toDateString() > $e->starts_at->toDateString()
            ? $e->ends_at->toDateTimeImmutable() : null;
        $this->zeit($event, $start, ! $e->all_day, $minuten, $ende);
    }

    /** Zeiten in UTC (von allen Kalendern verstanden), ganztägige als DATE. */
    private function zeit($event, DateTimeImmutable $start, bool $mitZeit, int $minuten, ?DateTimeImmutable $endeGanztags = null): void
    {
        if ($mitZeit) {
            $utc = $start->setTimezone(new DateTimeZone('UTC'));
            $event->add('DTSTART', $utc);
            $event->add('DTEND', $utc->modify('+'.$minuten.' minutes'));
        } else {
            $event->add('DTSTART', $start, ['VALUE' => 'DATE']);
            $event->add('DTEND', $endeGanztags ?? $start->modify('+1 day'), ['VALUE' => 'DATE']);
        }
    }
}
