<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\Lernender;
use App\Models\Pruefung;
use App\Models\User;
use App\Support\Einstellungen;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;

/**
 * iCal-Export zum Abonnieren: Lernende erhalten eigene Prüfungen und importierte Termine,
 * Berufsbildner/Admins die Prüfungen ihrer sichtbaren Lernenden. Zugriff über ein geheimes Token je Benutzer.
 */
final class CalendarExport
{
    private const int DAYS_BACK = 60;

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
        $betrieb = (string) Einstellungen::get(Einstellungen::BETRIEB_NAME, '');
        $kalender = new VCalendar([
            'PRODID' => '-//Notenportal//Agenda//DE',
            'X-WR-CALNAME' => 'Notenportal'.($betrieb !== '' ? ' '.$betrieb : '').' – Prüfungen',
            'X-WR-TIMEZONE' => (string) config('app.timezone'),
            'X-PUBLISHED-TTL' => 'PT1H',
            'REFRESH-INTERVAL' => 'PT1H',
        ]);
        $kalender->{'REFRESH-INTERVAL'}['VALUE'] = 'DURATION';

        $lernender = $user->lernender;
        $ids = $lernender ? [(int) $lernender->lernender_id] : Lernender::sichtbarFuer($user)->pluck('lernender_id')->map(fn ($id) => (int) $id)->all();
        $mitName = $lernender === null;

        Pruefung::query()->whereIn('lernender_id', $ids ?: [0])
            ->whereDate('datum', '>=', now()->subDays(self::DAYS_BACK)->toDateString())
            ->with(['fach', 'modul', 'lernender.benutzer', 'note'])->orderBy('datum')->get()
            ->each(fn (Pruefung $p) => $this->pruefung($kalender, $p, $mitName));

        if ($lernender) {
            CalendarEvent::query()->where('lernender_id', $lernender->lernender_id)->where('kind', CalendarEvent::APPOINTMENT)
                ->where('starts_at', '>=', now()->subDays(self::DAYS_BACK))->orderBy('starts_at')->get()
                ->each(fn (CalendarEvent $e) => $this->termin($kalender, $e));
        }

        return $kalender->serialize();
    }

    private function pruefung(VCalendar $kalender, Pruefung $p, bool $mitName): void
    {
        $name = $mitName ? ($p->lernender?->benutzer?->vorname.' '.$p->lernender?->benutzer?->nachname).': ' : '';
        $titel = trim($p->bezeichnung().($p->titel ? ' – '.$p->titel : ''));
        $zeilen = array_filter([
            $p->pruefungsart ? 'Prüfungsart: '.$p->pruefungsart : null,
            $p->dauer_minuten ? 'Dauer: '.$p->dauer_minuten.' Minuten' : null,
            $p->hilfsmittel ? 'Hilfsmittel: '.$p->hilfsmittel : null,
            'Gewichtung: '.rtrim(rtrim(number_format((float) $p->gewichtung_prozent, 2, '.', ''), '0'), '.').' %',
            $p->note ? 'Note: '.rtrim(rtrim(number_format((float) $p->note->note_wert, 2, '.', ''), '0'), '.') : null,
            $p->stoff ? "\nPrüfungsstoff:\n".$p->stoff : null,
        ]);

        $event = $kalender->add('VEVENT', [
            'UID' => 'pruefung-'.$p->pruefung_id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'SUMMARY' => $name.'Prüfung '.$titel,
            'DTSTAMP' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            'DESCRIPTION' => implode("\n", $zeilen),
            'CATEGORIES' => 'Prüfung',
            'URL' => route('learner.exams.index'),
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
        ]);
        if ($e->description) {
            $event->add('DESCRIPTION', $e->description);
        }
        if ($e->location) {
            $event->add('LOCATION', $e->location);
        }
        $start = $e->starts_at->toDateTimeImmutable();
        $minuten = $e->ends_at ? max(1, (int) $e->starts_at->diffInMinutes($e->ends_at)) : 60;
        $this->zeit($event, $start, ! $e->all_day, $minuten);
    }

    /** Zeiten in UTC (von allen Kalendern verstanden), ganztägige als DATE. */
    private function zeit($event, DateTimeImmutable $start, bool $mitZeit, int $minuten): void
    {
        if ($mitZeit) {
            $utc = $start->setTimezone(new DateTimeZone('UTC'));
            $event->add('DTSTART', $utc);
            $event->add('DTEND', $utc->modify('+'.$minuten.' minutes'));
        } else {
            $event->add('DTSTART', $start, ['VALUE' => 'DATE']);
            $event->add('DTEND', $start->modify('+1 day'), ['VALUE' => 'DATE']);
        }
    }
}
