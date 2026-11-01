<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Datenbank vergleicht E-Mails ohne Gross-/Kleinschreibung und ohne Kombinationszeichen; der
 * Login-Zähler zählte den Rohtext. Jede Schreibweise bekam so einen eigenen Zähler und die Sperre
 * nach fünf Fehlversuchen liess sich umgehen (Funktionsprüfung Runde 11).
 */
class LoginSperreSchreibweisenTest extends TestCase
{
    private function versuche(array $mails): array
    {
        $antworten = [];
        foreach ($mails as $mail) {
            $this->post('/login', ['email' => $mail, 'password' => 'falsch']);
            $antworten[] = json_encode(session('errors')?->get('email'), JSON_UNESCAPED_UNICODE);
        }

        return $antworten;
    }

    #[Test]
    public function schreibweisen_derselben_adresse_teilen_sich_den_zaehler(): void
    {
        Cache::flush();
        User::factory()->lernender()->create(['email' => 'max.muster@example.local']);

        $antworten = $this->versuche([
            'max.muster@example.local',
            'max.muster'.mb_chr(0x0350).'@example.local',
            'MAX.Muster@example.local',
            'max.muster'.mb_chr(0x0300).'@example.local',
            'max.muster'.mb_chr(0x0301).mb_chr(0x0360).'@example.local',
            'max.muster'.str_repeat(mb_chr(0x0350), 7).'@example.local',
        ]);

        foreach (array_slice($antworten, 0, 5) as $antwort) {
            $this->assertStringNotContainsString('Zu viele', (string) $antwort);
        }
        $this->assertStringContainsString('Zu viele', (string) $antworten[5]);
    }

    #[Test]
    public function gesperrte_variante_laesst_auch_richtiges_passwort_nicht_durch(): void
    {
        Cache::flush();
        User::factory()->lernender()->create(['email' => 'max.muster@example.local']);

        $this->versuche(array_map(fn ($k) => 'max.muster'.str_repeat(mb_chr(0x0350), $k).'@example.local', range(1, 5)));
        $this->post('/login', ['email' => 'max.muster@example.local', 'password' => UserFactory::PASSWORT]);

        $this->assertGuest();
    }

    #[Test]
    public function andere_adresse_wird_nicht_mitgesperrt(): void
    {
        Cache::flush();
        $this->versuche(array_map(fn ($k) => 'max.muster'.str_repeat(mb_chr(0x0350), $k).'@example.local', range(1, 6)));

        $antwort = $this->versuche(['anna.beispiel@example.local'])[0];
        $this->assertStringNotContainsString('Zu viele', (string) $antwort);
    }
}
