<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Der Verweis auf die offizielle Modulbeschreibung in der Notenliste der Lernenden.
 *
 * Dass App\Support\Modulbaukasten die Adresse richtig baut, prüft tests/Unit/Support/ModulbaukastenTest.php.
 * Hier geht es um die Strecke danach: der Controller legt den Verweis je Modulbelegung bei, und die Notenliste
 * muss ihn tatsächlich ausgeben. Ohne diesen Test bliebe unbemerkt, wenn der Wert unterwegs verloren geht –
 * gebaut ist nicht sichtbar.
 */
class ModulVerweisTest extends TestCase
{
    private int $lehrberuf;

    private int $modul;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => 'M987', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2',
            'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
    }

    /** Eine Note auf das Modul erzeugt die Belegung, an der der Verweis hängt. */
    private function notenliste(): TestResponse
    {
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create());
        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => '2026-03-02',
            'note_wert' => '5.0', 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();

        return $this->get(route('learner.grades.index', ['semester_id' => $this->semester]))->assertOk();
    }

    #[Test]
    public function die_notenliste_verweist_mit_bekannter_version_auf_die_modulbeschreibung(): void
    {
        DB::table('module')->where('modul_id', $this->modul)->update(['version' => '2']);

        $this->notenliste()
            ->assertSee('https://www.modulbaukasten.ch/module/987/2/de-DE')
            ->assertSee('Modulbeschreibung');
    }

    #[Test]
    public function ohne_version_erscheint_kein_geratener_verweis(): void
    {
        // Eine geratene Adresse antwortet im Modulbaukasten mit HTTP 200 und einer Fehlerseite,
        // wäre also nicht als falsch erkennbar. Darum lieber gar kein Verweis.
        $this->notenliste()
            ->assertDontSee('modulbaukasten.ch')
            ->assertDontSee('Modulbeschreibung');
    }
}
