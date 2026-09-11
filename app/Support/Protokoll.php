<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Aktivitaet;
use App\Models\Betreuung;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Aktivitätsprotokoll (Audit-Log) für Admins: schreibt sicherheitsrelevante Aktionen in die
 * Tabelle `aktivitaeten` (Migration 2026_09_12_000010). Bis die Hauptsession migriert hat, gibt
 * es die Tabelle auf Prod noch nicht – verfuegbar() prüft das gecacht, Schreibfehler brechen die
 * eigentliche Aktion nie ab (try/catch + Log::warning).
 */
final class Protokoll
{
    // Anmeldung (app/Listeners)
    public const string AUTH_ANMELDUNG_ERFOLGREICH = 'auth.anmeldung_erfolgreich';

    public const string AUTH_ANMELDUNG_FEHLGESCHLAGEN = 'auth.anmeldung_fehlgeschlagen';

    public const string AUTH_ABMELDUNG = 'auth.abmeldung';

    public const string AUTH_PASSWORT_GEAENDERT = 'auth.passwort_geaendert';

    public const string AUTH_DATENAUSKUNFT_EIGENE = 'auth.datenauskunft_eigene';

    // Admin
    public const string ADMIN_KONTO_ANGELEGT = 'admin.konto_angelegt';

    public const string ADMIN_ROLLEN_GEAENDERT = 'admin.rollen_geaendert';

    public const string ADMIN_KONTO_DEAKTIVIERT = 'admin.konto_deaktiviert';

    public const string ADMIN_KONTO_REAKTIVIERT = 'admin.konto_reaktiviert';

    public const string ADMIN_RESET_LINK_GESCHICKT = 'admin.reset_link_geschickt';

    public const string ADMIN_BETRIEB_GEAENDERT = 'admin.betrieb_geaendert';

    public const string ADMIN_SICHERUNG_ERSTELLT = 'admin.sicherung_erstellt';

    public const string ADMIN_SICHERUNG_HERUNTERGELADEN = 'admin.sicherung_heruntergeladen';

    public const string ADMIN_SICHERUNG_GELOESCHT = 'admin.sicherung_geloescht';

    public const string ADMIN_DATENAUSKUNFT_ERSTELLT = 'admin.datenauskunft_erstellt';

    public const string ADMIN_NOTE_GELOESCHT = 'admin.note_geloescht';

    public const string ADMIN_NOTENIMPORT_UEBERNOMMEN = 'admin.notenimport_uebernommen';

    public const string ADMIN_BETREUUNG_ANGELEGT = 'admin.betreuung_angelegt';

    public const string ADMIN_BETREUUNG_BEENDET = 'admin.betreuung_beendet';

    public const string ADMIN_SYSTEMHINWEIS_GEAENDERT = 'admin.systemhinweis_geaendert';

    public const string ADMIN_SITZUNGSDAUER_GEAENDERT = 'admin.sitzungsdauer_geaendert';

    public const string ADMIN_LOGO_GESPEICHERT = 'admin.logo_gespeichert';

    public const string ADMIN_LOGO_ENTFERNT = 'admin.logo_entfernt';

    public const string ADMIN_FEEDBACK_DUPLIKAT = 'admin.feedback_duplikat';

    public const string ADMIN_FEEDBACK_DUPLIKAT_AUFGEHOBEN = 'admin.feedback_duplikat_aufgehoben';

    /** Aktionsschlüssel => Anzeige-Label (deutsch, über __() übersetzt). */
    public const array LABELS = [
        self::AUTH_ANMELDUNG_ERFOLGREICH => 'Anmeldung erfolgreich',
        self::AUTH_ANMELDUNG_FEHLGESCHLAGEN => 'Anmeldung fehlgeschlagen',
        self::AUTH_ABMELDUNG => 'Abmeldung',
        self::AUTH_PASSWORT_GEAENDERT => 'Passwort geändert',
        self::AUTH_DATENAUSKUNFT_EIGENE => 'Eigene Datenauskunft heruntergeladen',
        self::ADMIN_KONTO_ANGELEGT => 'Konto angelegt',
        self::ADMIN_ROLLEN_GEAENDERT => 'Rollen geändert',
        self::ADMIN_KONTO_DEAKTIVIERT => 'Konto deaktiviert',
        self::ADMIN_KONTO_REAKTIVIERT => 'Konto reaktiviert',
        self::ADMIN_RESET_LINK_GESCHICKT => 'Reset-Link geschickt',
        self::ADMIN_BETRIEB_GEAENDERT => 'Betriebseinstellungen geändert',
        self::ADMIN_SICHERUNG_ERSTELLT => 'Sicherung erstellt',
        self::ADMIN_SICHERUNG_HERUNTERGELADEN => 'Sicherung heruntergeladen',
        self::ADMIN_SICHERUNG_GELOESCHT => 'Sicherung gelöscht',
        self::ADMIN_DATENAUSKUNFT_ERSTELLT => 'Datenauskunft erstellt',
        self::ADMIN_NOTE_GELOESCHT => 'Note gelöscht',
        self::ADMIN_NOTENIMPORT_UEBERNOMMEN => 'Notenimport übernommen',
        self::ADMIN_BETREUUNG_ANGELEGT => 'Betreuung angelegt',
        self::ADMIN_BETREUUNG_BEENDET => 'Betreuung beendet',
        self::ADMIN_SYSTEMHINWEIS_GEAENDERT => 'Systemhinweis geändert',
        self::ADMIN_SITZUNGSDAUER_GEAENDERT => 'Sitzungsdauer geändert',
        self::ADMIN_LOGO_GESPEICHERT => 'Logo gespeichert',
        self::ADMIN_LOGO_ENTFERNT => 'Logo entfernt',
        self::ADMIN_FEEDBACK_DUPLIKAT => 'Meldung als Duplikat markiert',
        self::ADMIN_FEEDBACK_DUPLIKAT_AUFGEHOBEN => 'Duplikat-Markierung aufgehoben',
    ];

    /** Schlüssel, die nie ins Protokoll dürfen (Passwörter, Token, Geheimnisse), unabhängig von Gross-/Kleinschreibung. */
    private const array SPERR_MUSTER = ['/passwort/i', '/password/i', '/token/i', '/secret/i', '/geheim/i'];

    private static ?bool $tabelleVorhanden = null;

    /** Wie lange Einträge aufbewahrt werden, bevor `notenportal:aktivitaeten-aufraeumen` sie löscht. */
    public const int AUFBEWAHRUNG_TAGE = 365;

    /**
     * Schreibt einen Protokolleintrag. Handelnde Person = der angemeldete Benutzer (null bei
     * fehlgeschlagener Anmeldung), IP aus dem aktuellen Request. Schlägt das Schreiben fehl
     * (z. B. DB nicht erreichbar) oder gibt es die Tabelle noch nicht, bricht die eigentliche
     * Aktion NIE ab – nur ein Log::warning.
     *
     * @param  array<string, mixed>  $details
     */
    public static function schreiben(string $aktion, ?Model $ziel = null, array $details = []): void
    {
        if (! self::verfuegbar()) {
            return;
        }

        try {
            Aktivitaet::create([
                'benutzer_id' => Auth::id(),
                'aktion' => $aktion,
                'ziel_typ' => $ziel ? class_basename($ziel) : null,
                'ziel_id' => $ziel?->getKey(),
                'ziel_bezeichnung' => $ziel ? mb_substr((string) self::bezeichnung($ziel), 0, 150) ?: null : null,
                'details' => ($gefiltert = self::gefiltert($details)) !== [] ? $gefiltert : null,
                'ip' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Aktivitätsprotokoll konnte nicht geschrieben werden: '.$e->getMessage(), ['aktion' => $aktion]);
        }
    }

    public static function label(string $aktion): string
    {
        return __(self::LABELS[$aktion] ?? $aktion);
    }

    /** Die Tabelle gibt es erst nach der Migration 2026_09_12_000010. Ist die DB nicht erreichbar, gilt «nicht verfügbar» (ohne zu cachen). */
    public static function verfuegbar(): bool
    {
        if (self::$tabelleVorhanden === null) {
            try {
                self::$tabelleVorhanden = Schema::hasTable('aktivitaeten');
            } catch (Throwable) {
                return false;
            }
        }

        return self::$tabelleVorhanden;
    }

    /** Kurzer, lesbarer Schnappschuss des Ziels (Name statt nur ID) – je nach Modelltyp. */
    private static function bezeichnung(Model $ziel): ?string
    {
        return match (true) {
            $ziel instanceof User => trim($ziel->vorname.' '.$ziel->nachname) ?: $ziel->email,
            $ziel instanceof Lernender => ($name = trim(($ziel->benutzer?->vorname ?? '').' '.($ziel->benutzer?->nachname ?? ''))) !== '' ? $name : null,
            $ziel instanceof Note => $ziel->titel ?: $ziel->fach?->name ?: $ziel->modulBelegung?->modul?->name,
            $ziel instanceof Betreuung => ($name = trim(($ziel->lernender?->benutzer?->vorname ?? '').' '.($ziel->lernender?->benutzer?->nachname ?? ''))) !== '' ? $name : null,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private static function gefiltert(array $details): array
    {
        $ergebnis = [];
        foreach ($details as $schluessel => $wert) {
            if (is_string($schluessel) && self::gesperrt($schluessel)) {
                continue;
            }
            $ergebnis[$schluessel] = is_array($wert) ? self::gefiltert($wert) : $wert;
        }

        return $ergebnis;
    }

    private static function gesperrt(string $schluessel): bool
    {
        foreach (self::SPERR_MUSTER as $muster) {
            if (preg_match($muster, $schluessel)) {
                return true;
            }
        }

        return false;
    }
}
