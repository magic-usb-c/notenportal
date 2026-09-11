<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Präferenz «Notenanzeige» (Block AD): steuert nur die Nachkommastellen von <x-note> im
 * bisherigen Standardfall (:stellen="1", Durchschnitte auf interaktiven Seiten). Ohne Präferenz
 * bzw. bei :stellen="2" (z. B. Verwaltungslisten) oder :stellen=null (einzelne Note) bleibt das
 * bisherige Verhalten unverändert.
 */
class NotenanzeigeAnzeigeTest extends TestCase
{
    #[Test]
    public function ohne_praeferenz_bleibt_das_bisherige_verhalten(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)
            ->view('components.note', ['wert' => 4.25, 'stellen' => 1])
            ->assertSee('4.3');
    }

    #[Test]
    public function praeferenz_zwei_nachkommastellen_wirkt_bei_stellen_eins(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['notenanzeige' => '2']]);

        $this->actingAs($user)
            ->view('components.note', ['wert' => 4.25, 'stellen' => 1])
            ->assertSee('4.25');
    }

    #[Test]
    public function praeferenz_wirkt_nicht_bei_fest_vorgegebenen_stellen(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['notenanzeige' => '2']]);

        $this->actingAs($user)
            ->view('components.note', ['wert' => 4.25, 'stellen' => 2])
            ->assertSee('4.25');

        // stellen=null (einzelne Note, z. B. Badge): unabhängig von der Präferenz unverändert.
        $this->actingAs($user)
            ->view('components.note', ['wert' => 4.25])
            ->assertSee('4.25');
    }

    #[Test]
    public function ungueltiger_gespeicherter_wert_faellt_still_auf_eine_nachkommastelle_zurueck(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['notenanzeige' => 'viele']]);

        $this->actingAs($user)
            ->view('components.note', ['wert' => 4.25, 'stellen' => 1])
            ->assertSee('4.3');
    }
}
