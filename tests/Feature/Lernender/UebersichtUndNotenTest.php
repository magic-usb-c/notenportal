<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\NotenSkala;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** GUI-Paket 3: Übersicht (Stand, Als Nächstes, Ziele) und Notenliste mit Drawer. */
class UebersichtUndNotenTest extends TestCase
{
    private int $modul;

    private int $lehrberuf;

    private int $semesterAlt;

    private int $semesterNeu;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '431', 'titel' => 'Aufträge durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        $this->semesterAlt = DB::table('semester')->insertGetId(['bezeichnung' => 'T-1', 'start_datum' => now()->subMonths(8)->toDateString(), 'end_datum' => now()->subMonths(2)->toDateString(), 'sortierung' => 10]);
        $this->semesterNeu = DB::table('semester')->insertGetId(['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->addDay()->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($this->user);
    }

    private function note(string $wert, ?string $datum = null, int $gewicht = 100): void
    {
        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul, 'pruefungsdatum' => $datum ?? now()->subDays(3)->toDateString(),
            'note_wert' => $wert, 'gewichtung_prozent' => $gewicht,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function stand_zeigt_bullet_graph_als_satz_und_keine_leeren_karten(): void
    {
        $this->note('5.0');
        $g = NotenSkala::grenzen();

        $this->get(route('learner.dashboard'))->assertOk()
            ->assertSee('Stand')
            ->assertSee('Als Nächstes')
            ->assertSee('aria-label="Gesamtschnitt 5.0, genügend ab '.NotenSkala::format($g['genuegend'], 1).', gut ab '.NotenSkala::format($g['gut'], 1).'"', false)
            ->assertDontSee('Ungenügend')
            ->assertDontSee('>Ziele<', false);
    }

    #[Test]
    public function dashboard_bietet_tabellenalternative_fuer_wo_stehe_ich_und_verlauf(): void
    {
        $this->note('5.0');

        $response = $this->get(route('learner.dashboard'))->assertOk();

        $response->assertSee('<table', false)
            ->assertSee('<caption', false)
            ->assertSee('scope="col"', false)
            ->assertSee('scope="row"', false);

        $response->assertSeeInOrder([
            'Wo stehe ich', 'Als Tabelle', 'Aufträge durchführen', '5.0',
            'Verlauf', 'Als Tabelle', 'Semesterschnitt',
        ], false);
    }

    #[Test]
    public function ungenuegende_zeugnisnote_steht_unter_als_naechstes(): void
    {
        $this->note('3.0');

        $this->get(route('learner.dashboard'))->assertOk()
            ->assertSeeInOrder(['Als Nächstes', 'Ungenügend: ', 'Zeugnisnote 3']);
    }

    #[Test]
    public function unerreichbares_ziel_ist_neutral_mit_hoechstwert_und_zielmarke(): void
    {
        $this->note('2.0', null, 60);
        $this->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$this->modul, 'datum' => now()->addWeek()->toDateString(), 'gewichtung_prozent' => 40, 'titel' => 'LB2',
        ])->assertSessionHasNoErrors();
        $this->post(route('learner.goals.store'), ['ziel' => 'gesamt', 'zielwert' => 5.5])->assertSessionHasNoErrors();

        $antwort = $this->get(route('learner.dashboard'))->assertOk()
            ->assertSee('>Ziele<', false)
            ->assertSee('Ziel 5.5', false)
            ->assertSee('erreichbar')
            ->assertDontSee('Nicht mehr erreichbar');
        $this->assertMatchesRegularExpression('/text-muted">\s*höchstens [\d.]+ erreichbar/', $antwort->getContent());
    }

    #[Test]
    public function statuszeile_zeigt_strich_fuer_leeres_semester(): void
    {
        $this->note('5.0', now()->subMonths(5)->toDateString());

        $this->get(route('learner.grades.index', ['semester_id' => $this->semesterNeu]))->assertOk()
            ->assertSeeInOrder(['<dt class="text-xs text-muted">Semester</dt>', '–', 'Gesamt', '5.0'], false)
            ->assertSee('Keine Noten in T-2');

        $this->get(route('learner.grades.index', ['semester_id' => $this->semesterAlt]))->assertOk()
            ->assertSee('Fach / Modul')
            ->assertSee('Aufträge durchführen')
            ->assertSee('aria-controls="noten-', false);
    }

    #[Test]
    public function dashboard_note_knopf_oeffnet_denselben_drawer_wie_grades(): void
    {
        $this->get(route('learner.dashboard'))->assertOk()
            ->assertSee("dispatch('np-note'", false)
            ->assertSee('npNoteDrawer(', false);
    }

    #[Test]
    public function fehler_im_dashboard_drawer_oeffnet_das_formular_dort_wieder(): void
    {
        $this->from(route('learner.dashboard'))
            ->post(route('learner.grades.store'), ['_drawer' => 'neu', 'typ' => 'modul', 'modul_id' => $this->modul, 'note_wert' => '9'])
            ->assertRedirect(route('learner.dashboard'))
            ->assertSessionHasErrors(['note_wert', 'pruefungsdatum']);

        $this->get(route('learner.dashboard'))->assertOk()
            ->assertSee('x-if="server"', false)
            ->assertSee('name="_drawer" value="neu"', false)
            ->assertSee('id="note_wert-fehler"', false);
    }

    #[Test]
    public function drawer_laedt_nur_das_formular_und_bleibt_bei_fremden_noten_zu(): void
    {
        $this->note('4.5');
        $noteId = (int) DB::table('noten')->where('lernender_id', $this->user->lernender->lernender_id)->value('note_id');

        $this->get(route('learner.grades.create', ['drawer' => 1]))->assertOk()
            ->assertSee('name="_drawer" value="neu"', false)
            ->assertDontSee('<html', false);
        $this->get(route('learner.grades.edit', ['note_id' => $noteId, 'drawer' => 1]))->assertOk()
            ->assertSee('value="bearbeiten:'.$noteId.'"', false)
            ->assertSee('name="_method" value="PUT"', false);
        $this->get(route('learner.grades.create'))->assertOk()->assertSee('<html', false)->assertDontSee('name="_drawer"', false);

        $fremd = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($fremd)->get(route('learner.grades.edit', ['note_id' => $noteId, 'drawer' => 1]))->assertNotFound();
    }

    #[Test]
    public function fehler_im_drawer_oeffnet_das_formular_wieder(): void
    {
        $this->from(route('learner.grades.index'))
            ->post(route('learner.grades.store'), ['_drawer' => 'neu', 'typ' => 'modul', 'modul_id' => $this->modul, 'note_wert' => '9'])
            ->assertRedirect(route('learner.grades.index'))
            ->assertSessionHasErrors(['note_wert', 'pruefungsdatum']);

        $this->get(route('learner.grades.index'))->assertOk()
            ->assertSee('x-if="server"', false)
            ->assertSee('name="_drawer" value="neu"', false)
            ->assertSee('id="note_wert-fehler"', false);
    }

    #[Test]
    public function fehler_beim_bearbeiten_zeigt_nur_eigene_noten_im_drawer(): void
    {
        $this->note('4.5');
        $eigene = (int) DB::table('noten')->where('lernender_id', $this->user->lernender->lernender_id)->value('note_id');

        $this->from(route('learner.grades.index'))
            ->put(route('learner.grades.update', $eigene), ['_drawer' => 'bearbeiten:'.$eigene, 'typ' => 'modul', 'modul_id' => $this->modul, 'note_wert' => '0'])
            ->assertSessionHasErrors('note_wert');
        $this->get(route('learner.grades.index'))->assertOk()
            ->assertSee('value="bearbeiten:'.$eigene.'"', false)
            ->assertSee(route('learner.grades.update', $eigene), false);

        $fremd = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($fremd)->withSession(['_old_input' => ['_drawer' => 'bearbeiten:'.$eigene]])
            ->get(route('learner.grades.index'))->assertOk()
            ->assertDontSee('x-if="server"', false);
    }
}
