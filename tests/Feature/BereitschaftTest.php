<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CalendarFeed;
use App\Models\MailLog;
use App\Models\User;
use App\Services\Betrieb\Sicherung;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use App\Support\Bereitschaft;
use App\Support\Einstellungen;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `php artisan notenportal:bereitschaft` (App\Support\Bereitschaft): rein lesende
 * Betriebsbereitschaftsprüfung vor dem Go-Live. Jeder Prüfpunkt einzeln rot (Ausgangslage
 * bzw. bewusst verschlechtert) und grün, dazu Exit-Code, --json und dass keine Geheimniswerte
 * (Passwörter, Mailziele, URLs) in der Ausgabe stehen.
 */
class BereitschaftTest extends TestCase
{
    private function statusVon(array $pruefungen, string $schluessel): string
    {
        foreach ($pruefungen as $p) {
            if ($p['schluessel'] === $schluessel) {
                return $p['status'];
            }
        }

        $this->fail("Prüfpunkt «{$schluessel}» fehlt.");
    }

    #[Test]
    public function app_env(): void
    {
        config(['app.env' => 'local']);
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_env'));

        config(['app.env' => 'production']);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_env'));
    }

    #[Test]
    public function app_debug(): void
    {
        config(['app.debug' => true]);
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_debug'));

        config(['app.debug' => false]);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_debug'));
    }

    #[Test]
    public function app_url(): void
    {
        config(['app.url' => 'http://172.26.14.101']);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_url'));

        config(['app.url' => 'https://portal.example.ch']);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'app_url'));
    }

    #[Test]
    public function trusted_hosts(): void
    {
        config(['app.trusted_hosts' => ' , ']);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'trusted_hosts'));

        config(['app.trusted_hosts' => '172.26.14.100,srv-lab-dva-003']);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'trusted_hosts'));
    }

    #[Test]
    public function session_secure(): void
    {
        config(['session.secure' => null]);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'session_secure'));

        config(['session.secure' => true]);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'session_secure'));
    }

    #[Test]
    public function mail_redirect(): void
    {
        Einstellungen::set(MailSettings::REDIRECT_TO, 'test@example.local');
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mail_redirect'));

        Einstellungen::set(MailSettings::REDIRECT_TO, null);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mail_redirect'));
    }

    #[Test]
    public function kopie_ausser_haus(): void
    {
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));

        Einstellungen::set(SicherungKopie::ZIEL, 'ordner');
        Einstellungen::set(SicherungKopie::PFAD, '/var/backups/notenportal');
        Einstellungen::set(SicherungKopie::LETZTE, now()->subHours(3)->toIso8601String());
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));
    }

    #[Test]
    public function kopie_ausser_haus_warnt_bei_fehlgeschlagener_alter_oder_fehlender_kopie(): void
    {
        Einstellungen::set(SicherungKopie::ZIEL, 'ordner');
        Einstellungen::set(SicherungKopie::PFAD, '/var/backups/notenportal');

        // Ziel eingerichtet, aber noch nie kopiert
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));

        // letzte erfolgreiche Kopie vor 20 Tagen
        Einstellungen::set(SicherungKopie::LETZTE, now()->subDays(20)->toIso8601String());
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));

        // frische Kopie, aber der letzte Lauf ist fehlgeschlagen
        Einstellungen::set(SicherungKopie::LETZTE, now()->subHours(1)->toIso8601String());
        Einstellungen::set(SicherungKopie::FEHLER, '13.10.2026 02:31 – ssh: Could not resolve hostname backup.beispielfirma.ch');
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));

        // Fehler weg, Kopie frisch: ok
        Einstellungen::set(SicherungKopie::FEHLER, null);
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kopie_ausser_haus'));
    }

    #[Test]
    public function kopie_ausser_haus_warnung_nennt_den_rohen_fehlertext_nicht(): void
    {
        Einstellungen::set(SicherungKopie::ZIEL, 'ssh');
        Einstellungen::set(SicherungKopie::HOST, 'backup.beispielfirma.ch');
        Einstellungen::set(SicherungKopie::PFAD, '/geheim/pfad');
        Einstellungen::set(SicherungKopie::LETZTE, now()->subHours(1)->toIso8601String());
        Einstellungen::set(SicherungKopie::FEHLER, 'ssh: Could not resolve hostname backup.beispielfirma.ch, mkdir "/geheim/pfad" failed');

        $eintrag = collect(app(Bereitschaft::class)->pruefen())->firstWhere('schluessel', 'kopie_ausser_haus');
        $this->assertSame(Bereitschaft::WARNUNG, $eintrag['status']);
        $this->assertStringContainsString('fehlgeschlagen', $eintrag['hinweis']);
        $this->assertStringNotContainsString('beispielfirma', $eintrag['hinweis']);
        $this->assertStringNotContainsString('/geheim/pfad', $eintrag['hinweis']);
    }

    #[Test]
    public function die_tagessicherung_laeuft_ausserhalb_der_zeitumstellung(): void
    {
        $ereignis = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'notenportal:sicherung'));
        $this->assertNotNull($ereignis);

        // Cron «Minute Stunde …»: zweites Feld ist die Stunde
        $felder = explode(' ', (string) $ereignis->expression);
        $stunde = (int) $felder[1];
        $minute = (int) $felder[0];

        // Die Stunde 02:xx kommt bei der Umstellung auf Winterzeit doppelt vor.
        $this->assertNotSame(2, $stunde);
        $this->assertNotSame(3, $stunde, 'Auch 03:xx liegt nahe an der Umstellung.');

        // Die anderen nächtlichen Läufe dürfen nicht zur selben Zeit starten.
        $belegt = ['03:15', '03:30', '03:45', '06:30', '18:00'];
        $this->assertNotContains(sprintf('%02d:%02d', $stunde, $minute), $belegt);
    }

    #[Test]
    public function sicherung(): void
    {
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'sicherung'));

        Einstellungen::set(Sicherung::LETZTE, now()->subDays(3)->toIso8601String());
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'sicherung'));

        Einstellungen::set(Sicherung::LETZTE, now()->subHours(2)->toIso8601String());
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'sicherung'));
    }

    #[Test]
    public function failed_jobs(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'failed_jobs'));

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Fehler', 'failed_at' => now(),
        ]);
        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'failed_jobs'));
    }

    #[Test]
    public function fehlgeschlagene_mails(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mails_fehlgeschlagen'));

        // MailLog::created_at ist absichtlich nicht in #[Fillable] – für ein gezieltes Testdatum
        // direkt über die Tabelle einfügen statt über MailLog::create().
        DB::table('mail_log')->insert([
            'type' => 'test', 'recipient' => 'jemand@beispiel.local', 'subject' => 'Test',
            'status' => MailLog::FAILED, 'error' => 'SMTP-Fehler', 'created_at' => now()->subDays(2),
        ]);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'mails_fehlgeschlagen'));
    }

    #[Test]
    public function kalenderabgleich(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kalenderabgleich'));

        $lernender = User::factory()->lernender()->create();
        CalendarFeed::create([
            'lernender_id' => $lernender->lernender->lernender_id, 'url' => 'https://schulnetz.example/ical/geheim',
            'last_status' => CalendarFeed::ERROR, 'last_error' => 'Zeitüberschreitung',
        ]);
        $this->assertSame(Bereitschaft::WARNUNG, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'kalenderabgleich'));
    }

    #[Test]
    public function offene_migrationen(): void
    {
        $this->assertSame(Bereitschaft::OK, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'migrationen'));

        $letzte = DB::table('migrations')->orderByDesc('id')->first();
        DB::table('migrations')->where('id', $letzte->id)->delete();

        $this->assertSame(Bereitschaft::FEHLER, $this->statusVon(app(Bereitschaft::class)->pruefen(), 'migrationen'));
    }

    #[Test]
    public function exit_code_folgt_dem_schlimmsten_status(): void
    {
        config(['app.env' => 'production', 'app.debug' => false]);
        Einstellungen::set(Sicherung::LETZTE, now()->toIso8601String());
        $this->assertTrue(app(Bereitschaft::class)->bereit());
        $this->artisan('notenportal:bereitschaft')->assertSuccessful();

        config(['app.env' => 'local']);
        $this->assertFalse(app(Bereitschaft::class)->bereit());
        $this->artisan('notenportal:bereitschaft')->assertFailed();
    }

    #[Test]
    public function json_ausgabe_enthaelt_alle_pruefpunkte(): void
    {
        Artisan::call('notenportal:bereitschaft', ['--json' => true]);
        $ausgabe = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('bereit', $ausgabe);
        $this->assertCount(12, $ausgabe['pruefungen']);
        foreach ($ausgabe['pruefungen'] as $p) {
            $this->assertContains($p['status'], [Bereitschaft::OK, Bereitschaft::WARNUNG, Bereitschaft::FEHLER]);
        }
    }

    #[Test]
    public function ausgabe_enthaelt_keine_geheimniswerte(): void
    {
        Einstellungen::set(MailSettings::REDIRECT_TO, 'geheim@beispielfirma.ch');
        Einstellungen::set(SicherungKopie::ZIEL, 'ssh');
        Einstellungen::set(SicherungKopie::HOST, 'backup.beispielfirma.ch');
        Einstellungen::set(SicherungKopie::PFAD, '/geheim/pfad');

        Artisan::call('notenportal:bereitschaft', ['--json' => true]);
        $ausgabe = Artisan::output();

        $this->assertStringNotContainsString('geheim@beispielfirma.ch', $ausgabe);
        $this->assertStringNotContainsString('backup.beispielfirma.ch', $ausgabe);
        $this->assertStringNotContainsString('/geheim/pfad', $ausgabe);

        $passwort = (string) config('database.connections.'.config('database.default').'.password');
        if ($passwort !== '') {
            $this->assertStringNotContainsString($passwort, $ausgabe);
        }
    }
}
