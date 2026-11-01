<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Seite «Externe Kalender» wiederholt keine Hinweise: kein Fusstext unter der Karte und kein
 * Schulnetz-Eintrag ohne Klickpfad in der Anleitung.
 */
class KalenderEinstellungenTexteTest extends TestCase
{
    #[Test]
    public function kalenderseite_hat_keine_doppelten_hinweise_und_keinen_schulnetz_eintrag_ohne_anleitung(): void
    {
        $seite = $this->actingAs(User::factory()->lernender()->create())->get(route('settings.calendar'))->assertOk();

        $seite->assertDontSee('erscheinen dann in der Agenda');
        $seite->assertSee(__('Woher bekomme ich die Adresse?'));
        $seite->assertSee(__('Nextcloud'));
        $seite->assertSee(__('Outlook'));
        $seite->assertSee(__('Google Kalender'));
        $seite->assertDontSee('<dt class="font-medium text-text">Schulnetz</dt>', false);
    }
}
