<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\Fach;
use App\Models\MailLog;
use App\Models\NotificationPreference;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Notifications\AccountMails;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** «Erneut senden» im Versandprotokoll darf keinen abgelaufenen Passwort-/Konto-Link verschicken; Dry-Run kennt Merker. */
class PasswortMailErneutSendenTest extends TestCase
{
    private function fehlgeschlagen(string $art): array
    {
        $user = User::factory()->lernender()->create(['email' => $art.'@firma.ch']);
        match ($art) {
            'vergessen' => $user->sendPasswordResetNotification(Password::broker('users')->createToken($user)),
            'konto' => AccountMails::accountCreated($user),
            'admin' => AccountMails::passwordResetByAdmin($user),
        };
        $log = MailLog::where('recipient', $user->email)->firstOrFail();
        $log->update(['status' => MailLog::FAILED]);

        return [$user, $log];
    }

    /** @return array<string, array{string, int}> Art => [Art, Tage bis zum Ablauf] */
    public static function arten(): array
    {
        return ['Passwort vergessen' => ['vergessen', 1], 'Konto eröffnet' => ['konto', 8], 'Admin-Reset' => ['admin', 8]];
    }

    #[Test]
    #[DataProvider('arten')]
    public function abgelaufener_link_wird_nicht_erneut_verschickt(string $art, int $tage): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        [, $log] = $this->fehlgeschlagen($art);
        Notification::fake(); // ursprünglicher Versand zählt nicht

        Carbon::setTestNow(now()->addDays($tage));
        try {
            $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))
                ->assertRedirect(route('admin.mail-log.index'))
                ->assertSessionHas('error', __('Der Link ist abgelaufen – Passwort im Benutzerkonto neu zurücksetzen.'))
                ->assertSessionMissing('success');
        } finally {
            Carbon::setTestNow();
        }

        Notification::assertNothingSent();
        $this->assertSame(1, MailLog::count());
        $this->assertSame(MailLog::FAILED, $log->fresh()->status);
    }

    #[Test]
    #[DataProvider('arten')]
    public function gueltiger_link_wird_erneut_verschickt(string $art, int $tage): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        [$user, $log] = $this->fehlgeschlagen($art);

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))
            ->assertSessionHas('success');

        $this->assertSame(2, MailLog::count());
        $this->assertSame(MailLog::RETRIED, $log->fresh()->status);
    }

    #[Test]
    public function bereits_eingeloester_link_wird_nicht_erneut_verschickt(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        [$user, $log] = $this->fehlgeschlagen('konto');
        Password::broker('invites')->deleteToken($user);

        $this->actingAs($admin)->post(route('admin.mail-log.retry', $log->id))->assertSessionHas('error');

        $this->assertSame(1, MailLog::count());
    }

    #[Test]
    public function dry_run_uebergeht_bereits_verschickte_erinnerungen(): void
    {
        Notification::fake();
        $user = User::factory()->lernender()->create(['email' => 'l@firma.ch']);
        Pruefung::create([
            'lernender_id' => $user->lernender->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Testat', 'datum' => today()->addDays(2)->toDateString(), 'gewichtung_prozent' => 50,
        ]);

        $this->artisan('notifications:check', ['--dry-run' => true])->expectsOutputToContain('[dry-run]')->assertSuccessful();

        $this->artisan('notifications:check')->assertSuccessful();
        $this->assertSame(1, MailLog::where('type', 'exam_reminder')->count());

        // Der echte Lauf hat gemeldet: eine Vorschau darf denselben Fall nicht mehr aufführen
        $this->artisan('notifications:check', ['--dry-run' => true])->doesntExpectOutputToContain('[dry-run]')->assertSuccessful();
    }

    #[Test]
    public function dry_run_uebergeht_empfaenger_die_den_anlass_abbestellt_haben(): void
    {
        Notification::fake();
        $user = User::factory()->lernender()->create(['email' => 'l@firma.ch']);
        Pruefung::create([
            'lernender_id' => $user->lernender->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Testat', 'datum' => today()->addDays(2)->toDateString(), 'gewichtung_prozent' => 50,
        ]);
        NotificationPreference::forceCreate(['user_id' => $user->benutzer_id, 'type' => NotificationCatalog::EXAM_REMINDER, 'frequency' => NotificationCatalog::NEVER]);

        // Der echte Lauf stellt nichts zu, also führt auch die Vorschau den Fall nicht auf
        $this->artisan('notifications:check', ['--dry-run' => true])->doesntExpectOutputToContain('[dry-run] Prüfung ')->assertSuccessful();
        // Gegenprobe: ohne Abbestellung steht er in der Vorschau
        NotificationPreference::query()->delete();
        $this->artisan('notifications:check', ['--dry-run' => true])->expectsOutputToContain('[dry-run] Prüfung ')->assertSuccessful();
    }
}
