<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Betreuung;
use App\Models\Note;
use App\Models\User;
use App\Services\Uebersicht;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UngeseheneNotenProBbTest extends TestCase
{
    #[Test]
    public function spalte_neu_zaehlt_nur_ungesehene_noten_aktiver_betreuter_lernender(): void
    {
        $bb = User::factory()->berufsbildner()->create(['vorname' => 'Berta', 'nachname' => 'Zaehler']);
        $aktiv = User::factory()->lernender()->create();
        $inaktiv = User::factory()->lernender()->create(['aktiv' => false]);

        foreach ([$aktiv, $inaktiv] as $u) {
            Betreuung::factory()->create([
                'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
                'lernender_id' => $u->lernender->lernender_id,
            ]);
        }

        Note::factory()->count(2)->create(['lernender_id' => $aktiv->lernender->lernender_id]);
        $gesehen = Note::factory()->create(['lernender_id' => $aktiv->lernender->lernender_id]);
        Note::factory()->count(3)->create(['lernender_id' => $inaktiv->lernender->lernender_id]);

        DB::table('noten_gesehen')->insert([
            'note_id' => $gesehen->note_id,
            'viewer_benutzer_id' => $bb->benutzer_id,
            'gesehen_am' => now()->addMinute(),
        ]);

        $zeile = collect(app(Uebersicht::class)->admin()['proBb'])->firstWhere('name', 'Berta Zaehler');

        $this->assertNotNull($zeile);
        $this->assertSame(2, $zeile->neu);
    }

    #[Test]
    public function zaehler_mehrerer_berufsbildner_bleiben_getrennt(): void
    {
        $bbA = User::factory()->berufsbildner()->create(['vorname' => 'Anna', 'nachname' => 'Erste']);
        $bbB = User::factory()->berufsbildner()->create(['vorname' => 'Bruno', 'nachname' => 'Zweiter']);
        $nurA = User::factory()->lernender()->create();
        $beide = User::factory()->lernender()->create();

        foreach ([[$bbA, $nurA], [$bbA, $beide], [$bbB, $beide]] as [$bb, $u]) {
            Betreuung::factory()->create([
                'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
                'lernender_id' => $u->lernender->lernender_id,
            ]);
        }

        Note::factory()->count(3)->create(['lernender_id' => $nurA->lernender->lernender_id]);
        $geteilt = Note::factory()->create(['lernender_id' => $beide->lernender->lernender_id]);

        // Nur Bruno hat die gemeinsame Note gesehen – Annas Zähler bleibt davon unberührt.
        DB::table('noten_gesehen')->insert([
            'note_id' => $geteilt->note_id,
            'viewer_benutzer_id' => $bbB->benutzer_id,
            'gesehen_am' => now()->addMinute(),
        ]);

        $proBb = collect(app(Uebersicht::class)->admin()['proBb']);

        $this->assertSame(4, $proBb->firstWhere('name', 'Anna Erste')->neu);
        $this->assertSame(0, $proBb->firstWhere('name', 'Bruno Zweiter')->neu);
    }
}
