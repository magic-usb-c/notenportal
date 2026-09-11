<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Dokument;
use App\Models\Lernender;
use App\Models\Pruefung;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

/**
 * Gleicht einen abonnierten iCal-Kalender ab: Termine und Lektionen landen in calendar_events,
 * Prüfungen mit erkanntem Modul/Fach zusätzlich in pruefungen (Felder aus dem Beschreibungstext).
 * Eigene Notizen, verknüpfte Note und Anhänge einer Prüfung bleiben beim Abgleich unberührt.
 */
final class CalendarSync
{
    private const int MAX_BYTES = 5_000_000;

    private const int DAYS_BACK = 60;

    private const int DAYS_AHEAD = 400;

    public function __construct(private readonly SchoolNetDescriptionParser $parser) {}

    /** @return array{events: int, exams: int, unmatched: int, removed: int} */
    public function sync(CalendarFeed $feed): array
    {
        try {
            $stats = $this->apply($feed, $this->fetch((string) $feed->url));
            $feed->forceFill([
                'last_synced_at' => now(), 'last_status' => CalendarFeed::OK, 'last_error' => null, 'events_count' => $stats['events'],
            ])->save();

            return $stats;
        } catch (\Throwable $e) {
            $feed->forceFill(['last_synced_at' => now(), 'last_status' => CalendarFeed::ERROR, 'last_error' => mb_substr($e->getMessage(), 0, 480)])->save();
            throw $e;
        }
    }

    /** @return array{events: int, exams: int, unmatched: int, removed: int} */
    public function apply(CalendarFeed $feed, string $ics): array
    {
        $kalender = Reader::read($ics, Reader::OPTION_FORGIVING | Reader::OPTION_IGNORE_INVALID_LINES);
        $von = now()->subDays(self::DAYS_BACK)->startOfDay();
        $bis = now()->addDays(self::DAYS_AHEAD);
        $kalender = $kalender->expand($von->toDateTime(), $bis->toDateTime());
        $zone = new DateTimeZone((string) config('app.timezone'));
        $lernender = $feed->lernender()->firstOrFail();
        $module = $this->moduleNachNummer($lernender);
        $faecher = $this->faecherNachKuerzel($lernender);

        $stats = ['events' => 0, 'exams' => 0, 'unmatched' => 0, 'removed' => 0];
        $gesehen = [];
        $pruefungenGesehen = [];

        DB::transaction(function () use ($kalender, $feed, $zone, $lernender, $module, $faecher, &$stats, &$gesehen, &$pruefungenGesehen, $von) {
            foreach ($kalender->select('VEVENT') as $event) {
                /** @var VEvent $event */
                if (! isset($event->DTSTART)) {
                    continue;
                }
                $uid = mb_substr(trim((string) ($event->UID ?? '')) ?: md5((string) $event->serialize()), 0, 255);
                $ganztags = ! $event->DTSTART->hasTime();
                $start = CarbonImmutable::instance($event->DTSTART->getDateTime())->setTimezone($zone);
                $ende = isset($event->DTEND) ? CarbonImmutable::instance($event->DTEND->getDateTime())->setTimezone($zone)
                    : (isset($event->DURATION) ? $start->add(\Sabre\VObject\DateTimeParser::parseDuration((string) $event->DURATION)) : null);
                $summary = trim((string) ($event->SUMMARY ?? ''));
                $beschreibung = trim((string) ($event->DESCRIPTION ?? ''));
                $ort = trim((string) ($event->LOCATION ?? '')) ?: null;

                $info = $this->parser->parse($summary, $beschreibung);
                $kind = $this->kind($info, $summary, $beschreibung);
                if (($kind === CalendarEvent::LESSON && ! $feed->import_lessons) || ($kind === CalendarEvent::APPOINTMENT && ! $feed->import_appointments)) {
                    continue;
                }

                $pruefungId = null;
                if ($kind === CalendarEvent::EXAM) {
                    $bezug = $this->bezug($info, $module, $faecher);
                    if ($bezug) {
                        $pruefungId = $this->pruefungSpeichern($lernender, $uid, $bezug, $info, $start, $ende, $ganztags, $ort);
                        $pruefungenGesehen[] = $pruefungId;
                        $stats['exams']++;
                    } else {
                        $stats['unmatched']++;
                    }
                }

                CalendarEvent::updateOrCreate(
                    ['calendar_feed_id' => $feed->id, 'uid' => $uid, 'starts_at' => $start->format('Y-m-d H:i:s')],
                    [
                        'lernender_id' => $lernender->lernender_id,
                        'kind' => $kind,
                        'summary' => mb_substr($summary !== '' ? $summary : 'Termin', 0, 255),
                        'description' => $beschreibung !== '' ? $beschreibung : null,
                        'location' => $ort ? mb_substr($ort, 0, 120) : null,
                        'ends_at' => $ende?->format('Y-m-d H:i:s'),
                        'all_day' => $ganztags,
                        'course_code' => $info['course_code'] ? mb_substr($info['course_code'], 0, 20) : null,
                        'pruefung_id' => $pruefungId,
                    ]
                );
                $gesehen[] = $uid.'|'.$start->format('Y-m-d H:i:s');
                $stats['events']++;
            }

            // Nicht mehr im Kalender: Termine im Abgleichsfenster entfernen
            $feed->events()->where('starts_at', '>=', $von)->get(['id', 'uid', 'starts_at'])
                ->reject(fn (CalendarEvent $e) => in_array($e->uid.'|'.$e->starts_at->format('Y-m-d H:i:s'), $gesehen, true))
                ->each(function (CalendarEvent $e) use (&$stats) {
                    $e->delete();
                    $stats['removed']++;
                });

            // Importierte, künftige Prüfungen, die verschwunden sind: unberührte löschen, sonst als abgesagt markieren
            Pruefung::query()->where('lernender_id', $lernender->lernender_id)->where('quelle', 'ical')
                ->whereNotIn('pruefung_id', $pruefungenGesehen ?: [0])->whereDate('datum', '>=', now()->toDateString())->whereNull('abgesagt_am')
                ->get()->each(function (Pruefung $p) {
                    $angefasst = $p->note_id || filled($p->notizen) || Dokument::where('pruefung_id', $p->pruefung_id)->exists();
                    $angefasst ? $p->update(['abgesagt_am' => now()]) : $p->delete();
                });
        });

        return $stats;
    }

    /** Lädt die Kalenderdatei; nur öffentliche http(s)-Ziele, höchstens 3 Weiterleitungen, 5 MB. */
    public function fetch(string $url): string
    {
        $url = self::normalizeUrl($url);
        for ($i = 0; $i < 4; $i++) {
            $ips = self::assertPublicUrl($url);
            $anfrage = Http::timeout(20)->connectTimeout(8)->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'Notenportal-Kalenderabgleich', 'Accept' => 'text/calendar, */*']);
            // Geprüfte IP für die eigentliche Verbindung festnageln (kein zweiter DNS-Lookup → kein DNS-Rebinding)
            $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
            if ($ips !== [] && ! filter_var($host, FILTER_VALIDATE_IP)) {
                $port = parse_url($url, PHP_URL_PORT) ?: (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
                $ip = str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0];
                $anfrage = $anfrage->withOptions(['curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$ip]]]);
            }
            $antwort = $anfrage->get($url);
            if ($antwort->redirect() && $antwort->header('Location')) {
                $url = (string) \GuzzleHttp\Psr7\UriResolver::resolve(new \GuzzleHttp\Psr7\Uri($url), new \GuzzleHttp\Psr7\Uri($antwort->header('Location')));
                continue;
            }
            if (! $antwort->successful()) {
                throw new RuntimeException('Kalender nicht erreichbar (HTTP '.$antwort->status().').');
            }
            $body = $antwort->body();
            if (strlen($body) > self::MAX_BYTES) {
                throw new RuntimeException('Kalenderdatei ist grösser als 5 MB.');
            }
            if (! str_contains($body, 'BEGIN:VCALENDAR')) {
                throw new RuntimeException('Die Adresse liefert keinen iCal-Kalender.');
            }

            return $body;
        }
        throw new RuntimeException('Zu viele Weiterleitungen.');
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return preg_replace('#^webcals?://#i', 'https://', $url) ?? $url;
    }

    /**
     * Schutz gegen Anfragen ins interne Netz (SSRF).
     *
     * @return list<string> geprüfte IP-Adressen (leer, wenn private Ziele erlaubt sind)
     */
    public static function assertPublicUrl(string $url): array
    {
        $teile = parse_url($url);
        if (! $teile || ! in_array(strtolower($teile['scheme'] ?? ''), ['http', 'https'], true) || empty($teile['host'])) {
            throw new RuntimeException('Bitte eine http(s)- oder webcal-Adresse angeben.');
        }
        if (config('notenportal.calendar_allow_private', false)) {
            return [];
        }
        $host = trim($teile['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );
        if ($ips === []) {
            throw new RuntimeException('Adresse nicht auflösbar.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Interne Adressen sind nicht erlaubt.');
            }
        }

        return array_values($ips);
    }

    private function kind(array $info, string $summary, string $beschreibung): string
    {
        if ($info['is_exam'] || preg_match('/\b(prüfung|pruefung|test|lb\s?\d|lernbeurteilung)\b/iu', $summary)) {
            return CalendarEvent::EXAM;
        }

        return $info['course_code'] !== null ? CalendarEvent::LESSON : CalendarEvent::APPOINTMENT;
    }

    /** @return array{0: string, 1: int}|null ['modul'|'fach', id] */
    private function bezug(array $info, array $module, array $faecher): ?array
    {
        if ($info['module_number'] !== null && isset($module[$info['module_number']])) {
            return ['modul', $module[$info['module_number']]];
        }
        $code = mb_strtoupper((string) $info['course_code']);

        return $code !== '' && isset($faecher[$code]) ? ['fach', $faecher[$code]] : null;
    }

    private function pruefungSpeichern(Lernender $lernender, string $uid, array $bezug, array $info, CarbonImmutable $start, ?CarbonImmutable $ende, bool $ganztags, ?string $ort): int
    {
        $titel = trim(implode(': ', array_filter([$info['label'], $info['title']])));
        $dauer = $info['duration_minutes'] ?? ($ende && ! $ganztags ? max(0, (int) $start->diffInMinutes($ende)) : null);
        $werte = [
            'fach_id' => $bezug[0] === 'fach' ? $bezug[1] : null,
            'modul_id' => $bezug[0] === 'modul' ? $bezug[1] : null,
            'titel' => $titel !== '' ? mb_substr($titel, 0, 150) : null,
            'datum' => $start->toDateString(),
            'uhrzeit' => $ganztags ? null : $start->format('H:i:s'),
            'dauer_minuten' => $dauer ?: null,
            'pruefungsart' => $info['exam_type'] ? mb_substr($info['exam_type'], 0, 150) : null,
            'hilfsmittel' => $info['aids'] ? mb_substr($info['aids'], 0, 255) : null,
            'stoff' => $info['material'],
            'raum' => ($info['room'] ?? $ort) ? mb_substr((string) ($info['room'] ?? $ort), 0, 60) : null,
            'lehrperson' => $info['teacher'] ? mb_substr($info['teacher'], 0, 60) : null,
            'quelle' => 'ical',
            'abgesagt_am' => null,
        ];
        if ($info['weight_percent'] !== null) {
            $werte['gewichtung_prozent'] = min(100, $info['weight_percent']);
        }

        $pruefung = Pruefung::firstOrNew(['lernender_id' => $lernender->lernender_id, 'extern_uid' => $uid]);
        $pruefung->fill($werte);
        $pruefung->gewichtung_prozent ??= 100;
        $pruefung->save();

        return (int) $pruefung->pruefung_id;
    }

    /** @return array<string, int> Modulnummer => modul_id (Module des Lehrberufs) */
    private function moduleNachNummer(Lernender $lernender): array
    {
        return DB::table('lehrberuf_module as lbm')->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->where('lbm.lehrberuf_id', $lernender->lehrberuf_id)
            ->pluck('m.modul_id', 'm.modul_nummer')->mapWithKeys(fn ($id, $nr) => [(string) $nr => (int) $id])->all();
    }

    /** @return array<string, int> Kürzel/Name (gross) => fach_id */
    private function faecherNachKuerzel(Lernender $lernender): array
    {
        $map = [];
        foreach (DB::table('faecher')->get(['fach_id', 'kurzname', 'name']) as $f) {
            foreach ([$f->kurzname, $f->name] as $k) {
                if (filled($k)) {
                    $map[mb_strtoupper(trim((string) $k))] ??= (int) $f->fach_id;
                }
            }
        }

        return $map;
    }
}
