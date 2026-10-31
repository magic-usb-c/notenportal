<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Notenbaum-Positionen (IPA, Schlussarbeit) sind keine Punkte im Verlauf (docs/notenlogik.md, Stichtagsreihe). */
class PositionKeinVerlaufspunktTest extends TestCase
{
    #[Test]
    public function eine_von_hand_gespeicherte_position_ist_kein_verlaufspunkt_und_bleibt_in_der_reihe(): void
    {
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'PVP', 'name' => 'Position-Verlauf EFZ']);
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $lehrberuf);
        Konfiguration::vergessen();
        $user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2025-08-01'])->create();
        $ipa = (int) DB::table('notenbaum_knoten')->where('code', 'ipa')->value('knoten_id');

        $this->actingAs($user)->put(route('learner.qualification.update'), ['werte' => [$ipa => '5.5']])
            ->assertSessionHas('success');
        $this->assertSame(1, DB::table('notenbaum_positionen')->count());

        $verlauf = $this->getJson(route('learner.dashboard', ['zeitraum' => 'alles']))->assertOk()->json('diagramm.verlauf');

        $this->assertSame([], $verlauf['punkte'], 'Die Position darf nicht als namenloser Prüfungspunkt erscheinen.');
        $werte = array_values(array_filter($verlauf['serien'][0]['werte'], fn ($w) => $w !== null));
        $this->assertNotEmpty($werte, 'Die Reihe rechnet die Position weiterhin mit.');
        $this->assertEqualsWithDelta(5.5, end($werte), 0.01);
    }
}
