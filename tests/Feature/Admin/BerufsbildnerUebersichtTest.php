<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Einstellungen;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class BerufsbildnerUebersichtTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function tiefer_schnitt_folgt_der_auswertung_und_der_eingestellten_grenze(): void
    {
        Einstellungen::set(Einstellungen::NOTE_GENUEGEND, '4.5');
        Konfiguration::vergessen();
        Semester::factory()->create();
        $bb = User::factory()->berufsbildner()->create();
        $lernender = User::factory()->lernender()->create();
        $this->betreue($bb, $lernender->lernender);
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lernender->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'FACH')->value('kategorie_id'),
        ]);
        $this->actingAs($lernender)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul->modul_id, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => 4.0,
        ])->assertSessionHasNoErrors();

        $antwort = $this->actingAs(User::factory()->admin()->create())->get(route('admin.trainers.index'))->assertOk();

        $antwort->assertSee('Ø &lt; 4.5', false);
        $this->assertSame(1, $antwort->viewData('stats')[$bb->berufsbildner->berufsbildner_id]->tief_avg);
    }
}
