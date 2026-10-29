<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CalendarFeed;
use App\Models\MailLog;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use Illuminate\Support\Facades\DB;

/**
 * Rein lesende Betriebsbereitschaftsprüfung vor dem Go-Live (docs/betrieb.md Go-Live-Checkliste),
 * aufgerufen über `php artisan notenportal:bereitschaft`. Verändert nichts, gibt nie Geheimniswerte
 * (Passwörter, Tokens, Mailadressen) aus – nur ob ein Wert gesetzt ist und wie der Status ist.
 */
final class Bereitschaft
{
    public const string OK = 'ok';

    public const string WARNUNG = 'warnung';

    public const string FEHLER = 'fehler';

    /** @return list<array{schluessel: string, label: string, status: string, hinweis: string}> */
    public function pruefen(): array
    {
        return [
            $this->appEnv(),
            $this->appDebug(),
            $this->appUrl(),
            $this->trustedHosts(),
            $this->sessionSecure(),
            $this->mailUmleitung(),
            $this->kopieAusserHaus(),
            $this->sicherung(),
            $this->failedJobs(),
            $this->fehlgeschlageneMails(),
            $this->kalenderAbgleich(),
            $this->offeneMigrationen(),
        ];
    }

    /** false, sobald mindestens ein Prüfpunkt "fehler" meldet (Kommandozeile: Exit 1). */
    public function bereit(): bool
    {
        foreach ($this->pruefen() as $p) {
            if ($p['status'] === self::FEHLER) {
                return false;
            }
        }

        return true;
    }

    private function appEnv(): array
    {
        $ok = config('app.env') === 'production';

        return $this->eintrag('app_env', 'APP_ENV', $ok ? self::OK : self::FEHLER, $ok
            ? 'APP_ENV=production.'
            : 'In .env vor dem Go-Live APP_ENV=production setzen (docs/betrieb.md Go-Live-Checkliste).');
    }

    private function appDebug(): array
    {
        $ok = config('app.debug') === false;

        return $this->eintrag('app_debug', 'APP_DEBUG', $ok ? self::OK : self::FEHLER, $ok
            ? 'APP_DEBUG=false.'
            : 'APP_DEBUG=true zeigt Fehlerdetails öffentlich – in .env auf false setzen.');
    }

    private function appUrl(): array
    {
        $ok = str_starts_with((string) config('app.url'), 'https://');

        return $this->eintrag('app_url', 'APP_URL https', $ok ? self::OK : self::WARNUNG, $ok
            ? 'APP_URL beginnt mit https://.'
            : 'APP_URL ist kein https:// – vor dem Betrieb ausserhalb des Lab-Netzes auf HTTPS umstellen (docs/betrieb.md «HTTPS»).');
    }

    private function trustedHosts(): array
    {
        $aktiv = TrustedHostPatterns::hosts() !== [];

        return $this->eintrag('trusted_hosts', 'TRUSTED_HOSTS', $aktiv ? self::OK : self::WARNUNG, $aktiv
            ? 'Host-Header sind auf TRUSTED_HOSTS und APP_URL begrenzt.'
            : 'TRUSTED_HOSTS ist leer – die App nimmt jeden Host-Header an. sudo ./install.sh trägt alle Namen und Adressen ein, oder von Hand in .env ergänzen und php artisan optimize ausführen (docs/betrieb.md «HTTPS»).');
    }

    private function sessionSecure(): array
    {
        $ok = config('session.secure') === true;

        return $this->eintrag('session_secure', 'session.secure', $ok ? self::OK : self::WARNUNG, $ok
            ? 'session.secure ist aktiv.'
            : 'SESSION_SECURE_COOKIE ist nicht aktiv – zusammen mit HTTPS vor dem Betrieb ausserhalb des Lab-Netzes aktivieren.');
    }

    private function mailUmleitung(): array
    {
        $gesetzt = MailSettings::redirectTo() !== null;

        return $this->eintrag('mail_redirect', 'Mail-Umleitung', $gesetzt ? self::WARNUNG : self::OK, $gesetzt
            ? 'Mail-Umleitung ist gesetzt – vor dem Go-Live auf der Seite Betrieb leeren, sonst gehen alle Mails an die Testadresse.'
            : 'Keine Mail-Umleitung eingerichtet.');
    }

    private function kopieAusserHaus(): array
    {
        $aktiv = app(SicherungKopie::class)->aktiv();

        return $this->eintrag('kopie_ausser_haus', 'Kopie ausser Haus', $aktiv ? self::OK : self::WARNUNG, $aktiv
            ? 'Ziel für Kopie ausser Haus eingerichtet.'
            : 'Kein Ziel für «Kopie ausser Haus» eingerichtet – auf der Seite Betrieb einrichten (Ordner oder SSH).');
    }

    private function sicherung(): array
    {
        $letzte = app(Sicherung::class)->letzte();
        $ok = $letzte && $letzte->gte(now()->subDays(2));

        return $this->eintrag('sicherung', 'Letzte Sicherung', $ok ? self::OK : self::FEHLER, $ok
            ? 'Letzte Sicherung ist weniger als 2 Tage alt.'
            : 'Keine Sicherung in den letzten 2 Tagen – prüfen, ob der tägliche Cron (02:30) läuft, ggf. auf der Seite Betrieb «Jetzt sichern».');
    }

    private function failedJobs(): array
    {
        $anzahl = DB::table('failed_jobs')->count();

        return $this->eintrag('failed_jobs', 'Fehlgeschlagene Jobs', $anzahl === 0 ? self::OK : self::FEHLER, $anzahl === 0
            ? 'Keine fehlgeschlagenen Warteschlangen-Jobs.'
            : 'Fehlgeschlagene Warteschlangen-Jobs vorhanden – prüfen (php artisan queue:failed).');
    }

    private function fehlgeschlageneMails(): array
    {
        $anzahl = MailLog::where('status', MailLog::FAILED)->where('created_at', '>=', now()->subDays(7))->count();

        return $this->eintrag('mails_fehlgeschlagen', 'Fehlgeschlagene Mails (7 Tage)', $anzahl === 0 ? self::OK : self::WARNUNG, $anzahl === 0
            ? 'Keine fehlgeschlagenen Mails in den letzten 7 Tagen.'
            : 'Fehlgeschlagene Mails in den letzten 7 Tagen – Versandprotokoll prüfen (Admin → Versandprotokoll).');
    }

    private function kalenderAbgleich(): array
    {
        $anzahl = CalendarFeed::query()->where('last_status', CalendarFeed::ERROR)
            ->whereHas('lernender', fn ($q) => $q->whereNull('geloescht_am'))->count();

        return $this->eintrag('kalenderabgleich', 'Kalenderabgleich', $anzahl === 0 ? self::OK : self::WARNUNG, $anzahl === 0
            ? 'Keine Kalenderabgleich-Fehler.'
            : 'Kalenderabgleich mit Fehlern – betroffene Feeds prüfen (Lernende → Agenda → Kalenderabo).');
    }

    private function offeneMigrationen(): array
    {
        $dateien = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $f) => pathinfo($f, PATHINFO_FILENAME))->sort()->values();
        $ausgefuehrt = DB::table('migrations')->pluck('migration');
        $offen = $dateien->diff($ausgefuehrt)->count();

        return $this->eintrag('migrationen', 'Offene Migrationen', $offen === 0 ? self::OK : self::FEHLER, $offen === 0
            ? 'Alle Migrationen ausgeführt.'
            : $offen.' Migration(en) noch nicht ausgeführt – mit dem Migrations-Benutzer einspielen (php artisan notenportal:migrate).');
    }

    /** @return array{schluessel: string, label: string, status: string, hinweis: string} */
    private function eintrag(string $schluessel, string $label, string $status, string $hinweis): array
    {
        return ['schluessel' => $schluessel, 'label' => $label, 'status' => $status, 'hinweis' => $hinweis];
    }
}
