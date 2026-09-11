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
}
