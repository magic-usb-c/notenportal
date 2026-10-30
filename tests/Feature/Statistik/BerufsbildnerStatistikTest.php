<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Semester;
use App\Models\User;
use App\Support\Einrichtung;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Berufsbildner-Dashboard (Hantel, S7), Lernendenliste (Punktstreifen, S8) und Cockpit (Verlauf, S1): die Seiten zeigen nur
 * aktiv betreute Personen, der Median erst ab drei Personen (GUI-R6 §6, §10).
 */
class BerufsbildnerStatistikTest extends TestCase
{
    use DiagrammTabellenHilfen;

    private int $kategorieId;

    private Lehrberuf $lehrberuf;

    /** @var list<Semester> */
    private array $semester = [];

    protected function setUp(): void
    {
        parent::setUp();
        Einrichtung::abschliessen();

        $this->kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $this->lehrberuf = Lehrberuf::factory()->create();

        $start = Carbon::parse('2020-08-01');
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $this->semester[] = Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]);
            $start = $ende->copy()->addDay();
        }
    }

    #[Test]
    public function die_liste_zeichnet_mit_zwei_betreuten_personen_keine_medianlinie_mit_drei_eine(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.0, $bb);
        $this->lernender('Brunner', 5.0, 5.0, $bb);

        $zwei = $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk();
        $zwei->assertSee('data-np-baustein="punktstreifen"', false);
        $zwei->assertDontSee('Median', false);

        $this->lernender('Camenzind', 4.5, 4.5, $bb);

        $drei = $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk();
        $drei->assertSee('Median 4.5', false);
        $drei->assertSee('<rect x=', false);
    }

    #[Test]
    public function nicht_betreute_person_erscheint_in_keinem_punkt_keiner_tabelle_keinem_json(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        foreach (['Ammann', 'Brunner', 'Camenzind'] as $name) {
            $this->lernender($name, 4.0, 4.5, $bb);
        }
        $fremd = $this->lernender('Zebrowski', 2.0, 2.0, User::factory()->berufsbildner()->create());

        foreach (['trainer.learners.index', 'trainer.dashboard'] as $route) {
            $html = $this->actingAs($bb)->get(route($route))->assertOk();
            $html->assertDontSee('Zebrowski', false);
            $html->assertDontSee('/learners/'.$fremd->lernender->lernender_id, false);

            $json = $this->actingAs($bb)->getJson(route($route))->assertOk();
            $this->assertStringNotContainsString('Zebrowski', $json->getContent());
        }

        $this->actingAs($bb)->get(route('trainer.learners.show', $fremd->lernender->lernender_id))->assertNotFound();
        $this->actingAs($bb)->getJson(route('trainer.learners.show', $fremd->lernender->lernender_id))->assertNotFound();
    }

    #[Test]
    public function dashboard_zeigt_die_hantel_und_fuehrt_unter_genuegend_mit_wort_und_link_ins_cockpit(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $schwach = $this->lernender('Ammann', 4.5, 3.0, $bb);
        $this->lernender('Brunner', 4.0, 5.0, $bb);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk();

        $html->assertSee('data-np-baustein="hantel"', false);
        $html->assertSee('Ammann', false);
        $html->assertSee('Brunner', false);
        $cockpit = route('trainer.learners.show', $schwach->lernender->lernender_id);
        $html->assertSee('href="'.$cockpit.'"', false);
        $html->assertSeeText('3.0 · '.__('ungenügend'));

        // Filter kritisch: nur die Person mit rotem Stand; ?status=… ist eine Whitelist, Fremdes fällt auf «alle»
        $kritisch = $this->actingAs($bb)->getJson(route('trainer.dashboard', ['status' => 'kritisch']))->assertOk();
        $this->assertSame('alle', $this->actingAs($bb)->getJson(route('trainer.dashboard', ['status' => 'unbekannt']))->json('filter.status'));
        foreach ($kritisch->json('diagramm.hantel.zeilen') as $zeile) {
            $this->assertSame('rot', $zeile['status']);
        }
    }

    #[Test]
    public function admin_und_berufsbildner_sehen_dieselbe_liste_mit_punktstreifen_und_sortierlink_verlauf(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.5, $bb);
        $this->lernender('Fremd', 5.0, 5.5, User::factory()->berufsbildner()->create());

        $trainer = $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk();
        $trainer->assertSee('data-np-liste', false);
        $trainer->assertSee('sort=delta', false);
        $trainer->assertSee('Ammann', false);
        $trainer->assertDontSee('Fremd,', false);

        $admin = $this->actingAs(User::factory()->admin()->create())->get(route('admin.learners.index'))->assertOk();
        $admin->assertSee('data-np-baustein="punktstreifen"', false);
        $admin->assertSee('Ammann', false);
        $admin->assertSee('Fremd', false);
        $this->assertSame(2, $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.learners.index'))->json('diagramm.punktstreifen.n'));
    }

    #[Test]
    public function filter_in_der_adresse_rendern_dashboard_liste_und_cockpit_ohne_fehler(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $a = $this->lernender('Ammann', 4.0, 4.5, $bb);
        $this->lernender('Brunner', 5.0, 3.0, $bb);

        $this->actingAs($bb)->get(route('trainer.dashboard', ['status' => 'kritisch', 'kategorie' => $this->kategorieId, 'sort' => 'name']))
            ->assertOk()
            ->assertSee('id="hantel-status"', false);
        $this->actingAs($bb)->get(route('trainer.learners.index', ['kategorie' => $this->kategorieId, 'sort' => 'delta', 'dir' => 'desc', 'lehrjahr' => 2]))
            ->assertOk();
        $this->actingAs($bb)->get(route('trainer.learners.show', ['lernender_id' => $a->lernender->lernender_id, 'zeitraum' => 'semester', 'achse' => 'semester', 'kategorie' => $this->kategorieId]))
            ->assertOk()
            ->assertSee('data-np-verlauf-chart', false);
    }

    #[Test]
    public function cockpit_zeigt_den_verlauf_nur_mit_noten(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $mit = $this->lernender('Ammann', 4.0, 4.5, $bb);
        $ohne = User::factory()->lernender([
            'lehrberuf_id' => $this->lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths(2)->toDateString(),
            'lehrende' => now()->addMonths(46)->toDateString(),
        ])->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $ohne->lernender->lernender_id]);

        $this->actingAs($bb)->get(route('trainer.learners.show', $mit->lernender->lernender_id))
            ->assertOk()
            ->assertSee('data-np-verlauf-chart', false);

        $this->actingAs($bb)->get(route('trainer.learners.show', $ohne->lernender->lernender_id))
            ->assertOk()
            ->assertDontSee('data-np-verlauf-chart', false);
    }

    #[Test]
    public function tabellen_von_hantel_und_punktstreifen_tragen_eine_beschriftung_und_zeilenkoepfe(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.5, $bb);
        $this->lernender('Brunner', 5.0, 3.0, $bb);

        $dashboard = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk();
        $this->pruefeZeilenkopf(
            $this->tabelleNach($dashboard->getContent(), 'data-np-baustein="hantel"'),
            $dashboard->viewData('statistik')['meta']['hantel']['titel'],
        );

        $liste = $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk();
        $this->pruefeZeilenkopf(
            $this->tabelleNach($liste->getContent(), 'data-np-baustein="punktstreifen"'),
            $liste->viewData('statistik')['meta']['punktstreifen']['titel'],
        );
    }

    #[Test]
    public function achsenziffern_des_punktstreifens_stehen_senkrecht_unter_den_punkten(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.0, $bb);
        $this->lernender('Brunner', 5.0, 5.0, $bb);

        $html = $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-np-baustein="punktstreifen".*?<\/section>/s', $html, $baustein));

        // Punkt: cx von 600 Einheiten; Ziffer: left in Prozent der gleichen Breite
        preg_match_all('/<circle cx="([\d.]+)"[^>]*>\s*<title>[^<]*, (\d\.\d)<\/title>/', $baustein[0], $punkte, PREG_SET_ORDER);
        $this->assertCount(2, $punkte);
        foreach ($punkte as [, $cx, $note]) {
            $this->assertSame(1, preg_match('/<span class="absolute -translate-x-1\/2" style="left: ([\d.]+)%">'.(int) $note.'<\/span>/', $baustein[0], $ziffer), 'Ziffer '.(int) $note.' fehlt');
            $this->assertEqualsWithDelta((float) $cx / 600 * 100, (float) $ziffer[1], 0.05, 'Ziffer '.(int) $note.' steht neben dem Punkt');
        }
    }

    #[Test]
    public function hantel_im_dashboard_haelt_namensspalte_und_achsenplatzhalter_gleich_breit(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.5, $bb);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-np-baustein="hantel".*?<\/section>/s', $html, $baustein));
        $this->assertGreaterThan(0, preg_match_all('/<li class="flex items-center gap-3[^"]*">\s*<span class="([^"]*)"/', $baustein[0], $spalten));

        $breiten = array_unique(array_map(fn (string $k) => preg_match('/\bw-\d+\b/', $k, $b) ? $b[0] : '', $spalten[1]));
        $this->assertCount(1, $breiten, 'Namensspalte und Achsenplatzhalter sind verschieden breit: '.implode(', ', $breiten));
        $this->assertNotContains('w-40', $breiten);
    }

    #[Test]
    public function cockpit_zeigt_den_rueckgang_zum_vorsemester_ohne_notenfarbe(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $zurueck = $this->lernender('Ammann', 5.0, 3.5, $bb);

        $html = $this->actingAs($bb)->get(route('trainer.learners.show', $zurueck->lernender->lernender_id))->assertOk()->getContent();

        // «▼ … zum Vorsemester»: Richtung steht im Zeichen und im Text, die Notenstufenfarbe gehört der Note
        $this->assertSame(1, preg_match('/<div class="([^"]*)">\s*<span aria-hidden="true">▼<\/span>[^<]*zum Vorsemester/u', $html, $delta), 'Rückgang fehlt');
        $this->assertStringNotContainsString('note-knapp', $delta[1]);
    }

    private function lernender(string $nachname, float $vorher, float $jetzt, ?User $bb = null): User
    {
        $user = User::factory()->lernender([
            'lehrberuf_id' => $this->lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths(18)->toDateString(),
            'lehrende' => now()->addMonths(30)->toDateString(),
        ])->create(['nachname' => $nachname]);

        if ($bb !== null) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id]);
        }

        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $this->kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        foreach ([[$this->semester[0], $vorher], [$this->semester[1], $jetzt]] as [$semester, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $user->lernender->lernender_id,
                'kategorie_id' => $this->kategorieId,
                'semester_id' => $semester->semester_id,
                'fach_id' => $fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($semester->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $user->benutzer_id,
            ]);
        }

        return $user;
    }
}
