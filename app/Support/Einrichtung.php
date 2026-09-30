<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Notifications\MailSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Geführte Ersteinrichtung: Schritte, Stand aus der DB, Vorlagen und Hilfen zum Anlegen.
 * Aktiv, solange `einrichtung_offen` = 1 (setzt der Installer beim ersten Admin-Konto).
 */
final class Einrichtung
{
    public const string OFFEN = 'einrichtung_offen';

    public const string KATEGORIEN_GEPRUEFT = 'einrichtung_kategorien';

    public const array SCHRITTE = [
        'operations' => 'Betrieb',
        'categories' => 'Kategorien',
        'semesters' => 'Semester',
        'professions' => 'Lehrberufe & Fächer',
        'modules' => 'Module',
        'people' => 'Personen',
        'mail' => 'E-Mail',
        'finish' => 'Abschluss',
    ];

    public static function offen(): bool
    {
        return Einstellungen::get(self::OFFEN) === '1';
    }

    public static function oeffnen(): void
    {
        Einstellungen::set(self::OFFEN, '1');
    }

    public static function abschliessen(): void
    {
        Einstellungen::set(self::OFFEN, '0');
    }

    public static function naechster(string $schritt): string
    {
        $schluessel = array_keys(self::SCHRITTE);
        $pos = array_search($schritt, $schluessel, true);

        return $schluessel[min(count($schluessel) - 1, ($pos === false ? 0 : $pos) + 1)];
    }

    /** @return array<string, array{erledigt: bool, info: string}> */
    public static function stand(): array
    {
        $anzahl = fn (string $tabelle, ?\Closure $filter = null) => (int) DB::table($tabelle)->when($filter, $filter)->count();
        $semester = DB::table('semester')->orderBy('sortierung')->pluck('bezeichnung');
        $lehrberufe = $anzahl('lehrberufe');
        $faecher = $anzahl('faecher');
        $module = $anzahl('lehrberuf_module');
        $bb = $anzahl('berufsbildner', fn ($q) => $q->whereNull('geloescht_am'));
        $lernende = $anzahl('lernende', fn ($q) => $q->whereNull('geloescht_am'));
        $name = (string) Einstellungen::get(Einstellungen::BETRIEB_NAME, '');

        return [
            'operations' => ['erledigt' => $name !== '', 'info' => $name],
            'categories' => ['erledigt' => Einstellungen::get(self::KATEGORIEN_GEPRUEFT) === '1', 'info' => __(':anzahl aktiv', ['anzahl' => $anzahl('kategorien', fn ($q) => $q->where('aktiv', 1))])],
            'semesters' => ['erledigt' => $semester->isNotEmpty(), 'info' => $semester->isEmpty() ? '' : $semester->first().' – '.$semester->last()],
            'professions' => ['erledigt' => $lehrberufe > 0, 'info' => __(':lehrberufe Lehrberufe · :faecher Fächer', ['lehrberufe' => $lehrberufe, 'faecher' => $faecher])],
            'modules' => ['erledigt' => $module > 0, 'info' => __(':anzahl Zuordnungen', ['anzahl' => $module])],
            'people' => ['erledigt' => $bb + $lernende > 0, 'info' => __(':bb Berufsbildner · :lernende Lernende', ['bb' => $bb, 'lernende' => $lernende])],
            'mail' => ['erledigt' => MailSettings::values()['source'] !== 'none', 'info' => ''],
            'finish' => ['erledigt' => ! self::offen(), 'info' => ''],
        ];
    }

    /**
     * Semester je Schuljahr: Herbst (…-1) und Frühling (…-2), lückenlos aneinander.
     *
     * @return list<array{bezeichnung: string, start_datum: string, end_datum: string}>
     */
    public static function semesterPlan(int $vonJahr, int $bisJahr, string $startHerbst, string $startFruehling): array
    {
        $plan = [];
        for ($jahr = $vonJahr; $jahr <= $bisJahr; $jahr++) {
            $kurz = sprintf('%02d/%02d', $jahr % 100, ($jahr + 1) % 100);
            $herbst = Carbon::parse($jahr.'-'.$startHerbst);
            $fruehling = Carbon::parse(($jahr + 1).'-'.$startFruehling);
            $naechsterHerbst = Carbon::parse(($jahr + 1).'-'.$startHerbst);
            $plan[] = ['bezeichnung' => $kurz.'-1', 'start_datum' => $herbst->toDateString(), 'end_datum' => $fruehling->copy()->subDay()->toDateString()];
            $plan[] = ['bezeichnung' => $kurz.'-2', 'start_datum' => $fruehling->toDateString(), 'end_datum' => $naechsterHerbst->copy()->subDay()->toDateString()];
        }

        return $plan;
    }

    /**
     * Eine Zeile pro Modul: «431 Aufträge durchführen», «ÜK-106: Datenbanken abfragen», «M319 – Applikationen entwerfen».
     *
     * @return array{module: list<array{nummer: string, titel: string}>, fehler: list<string>}
     */
    public static function moduleAusText(?string $text): array
    {
        $module = [];
        $fehler = [];
        foreach (preg_split('/\R/u', (string) $text) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '') {
                continue;
            }
            if (preg_match('/^(?:(?:ÜK|UEK|M)[\s-]*)?([A-Za-z]{0,3}\d{2,4}[a-z]?)\s*[-–:.)]?\s+(.{2,255})$/iu', $zeile, $m)) {
                $module[] = ['nummer' => strtoupper($m[1]), 'titel' => trim($m[2])];
            } else {
                $fehler[] = $zeile;
            }
        }

        return ['module' => $module, 'fehler' => $fehler];
    }

    /** Eindeutiger, alphanumerischer Benutzername aus Vor- und Nachname. */
    public static function benutzername(string $vorname, string $nachname): string
    {
        $basis = substr((string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($vorname.$nachname))), 0, 40) ?: 'konto';
        $name = $basis;
        for ($i = 2; DB::table('benutzer')->where('benutzername', $name)->exists(); $i++) {
            $name = $basis.$i;
        }

        return $name;
    }
}
