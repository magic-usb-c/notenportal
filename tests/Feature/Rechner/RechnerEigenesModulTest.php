<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\Lernender;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Rechner;
use App\Services\Noten\NoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RechnerEigenesModulTest extends TestCase
{
    private User $user;

    private Lernender $lernender;

    private int $fremdesModul;

    protected function setUp(): void
    {
        parent::setUp();

        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Testberuf EFZ']);
        $eigenes = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf, 'modul_id' => $eigenes,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id'),
        ]);
        $this->fremdesModul = DB::table('module')->insertGetId(['modul_nummer' => '909', 'titel' => 'Fremdmodul Omega']);
        DB::table('semester')->insert(['bezeichnung' => 'S', 'start_datum' => '2026-02-01', 'end_datum' => '2026-12-31', 'sortierung' => 10]);
        Konfiguration::vergessen();

        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2026-01-01'])->create();
        $this->lernender = $this->user->lernender;
    }

    private function simuliere(int $modulId): TestResponse
    {
        return $this->actingAs($this->user)->postJson(route('learner.grades.calculator.simulate'), ['zeilen' => [[
            'typ' => 'modul', 'modul_id' => $modulId, 'pruefungsdatum' => '2026-03-02',
            'note_wert' => 4.5, 'gewichtung_prozent' => 100,
        ]]]);
    }

    #[Test]
    public function selbst_hinzugefuegtes_modul_ohne_noten_laesst_sich_im_drawer_rechnen(): void
    {
        $this->actingAs($this->user)->post(route('modules.enroll', $this->fremdesModul))->assertSessionHasNoErrors();
        // Auswahl der Notenerfassung und Rechner kennen dieselbe Menge
        $this->assertTrue(app(NoteService::class)->modulesForLernender((int) $this->lernender->lernender_id)->contains('modul_id', $this->fremdesModul));

        $this->simuliere($this->fremdesModul)->assertOk()->assertJsonStructure(['vergleich', 'promotion']);

        $eintrag = collect(app(Rechner::class)->katalog($this->lernender)['module'])->firstWhere('id', $this->fremdesModul);
        $this->assertNotNull($eintrag);
        $this->assertTrue($eintrag['erfassbar']);
        $this->assertGreaterThan(0, $eintrag['kategorie_id']);
    }

    #[Test]
    public function modul_ohne_eigene_belegung_bleibt_ungueltig(): void
    {
        $this->simuliere($this->fremdesModul)->assertStatus(422)->assertJsonValidationErrors('zeilen.0.element');
    }
}
