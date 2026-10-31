<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Funktionsprüfung «sicherheit»: POST /reset-password verrät nicht, ob zu einer Adresse ein Konto existiert. */
class PasswortZuruecksetzenEnumerationTest extends TestCase
{
    private function fehlermeldung(string $email): string
    {
        $passwort = 'Sehr-Lang-Passwort-9182!x';
        $this->from('/reset-password/abc')->post('/reset-password', [
            'token' => 'falsch', 'email' => $email,
            'password' => $passwort, 'password_confirmation' => $passwort,
        ])->assertSessionHasErrors('email');

        return session('errors')->first('email');
    }

    #[Test]
    public function bekannte_und_unbekannte_adresse_erhalten_dieselbe_meldung(): void
    {
        User::factory()->berufsbildner()->create(['email' => 'vorhanden-x@example.com']);

        $bekannt = $this->fehlermeldung('vorhanden-x@example.com');
        $unbekannt = $this->fehlermeldung('unbekannt-x@example.com');

        $this->assertSame($bekannt, $unbekannt);
        $this->assertSame(__('passwords.token'), $unbekannt);
    }
}
