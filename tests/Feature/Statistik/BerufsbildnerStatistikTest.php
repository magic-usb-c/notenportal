<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Betreuung;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * R6-09: Berufsbildner-Dashboard (Hantel «Wer hat sich bewegt»), Lernendenliste (Punktstreifen, Bullet, Sparkline) und
 * Cockpit-Verlauf. Gruppenwerte erst ab drei Personen, und nichts, was der Berufsbildner nicht betreut, taucht in
 * einem Punkt, einer Tabelle, einem Link oder im JSON auf.
 */
class BerufsbildnerStatistikTest extends TestCase
{
    private User $bb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bb = User::factory()->berufsbildner()->create();
    }

    /** Betreute Person mit einer Note, eindeutigem Nachnamen. */
    private function betreut(string $nachname, float $note = 4.5, ?User $bb = null): User
    {
        $user = User::factory()->lernender()->create(['nachname' => $nachname]);
        Betreuung::factory()->create([
            'berufsbildner_id' => ($bb ?? $this->bb)->berufsbildner->berufsbildner_id,
            'lernender_id' => $user->lernender->lernender_id,
        ]);
        Note::factory()->create(['lernender_id' => $user->lernender->lernender_id, 'note_wert' => $note]);

        return $user;
    }

    /** Person eines anderen Berufsbildners mit Note. */
    private function fremd(string $nachname): User
    {
        return $this->betreut($nachname, 3.0, User::factory()->berufsbildner()->create());
    }

    #[Test]
    public function die_median_linie_gibt_es_erst_ab_drei_betreuten_personen(): void
    {
        $this->betreut('Erstanna', 4.0);
        $this->betreut('Zweitbert', 5.0);

        $paket = $this->actingAs($this->bb)->getJson(route('trainer.learners.index'))->assertOk()->json();
        $this->assertNull($paket['diagramm']['punktstreifen']['median']);
        $this->assertCount(2, $paket['diagramm']['punktstreifen']['punkte']);
        $html = $this->actingAs($this->bb)->get(route('trainer.learners.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString(__('Median'), $html);

        $this->betreut('Drittina', 6.0);

        $paket = $this->actingAs($this->bb)->getJson(route('trainer.learners.index'))->assertOk()->json();
        $this->assertNotNull($paket['diagramm']['punktstreifen']['median']);
        $html = $this->actingAs($this->bb)->get(route('trainer.learners.index'))->assertOk()->getContent();
        $this->assertStringContainsString(__('Median'), $html);
    }

    #[Test]
    public function eine_nicht_betreute_person_erscheint_in_keinem_punkt_keiner_tabelle_keinem_json_und_keinem_link(): void
    {
        $eigene = $this->betreut('Eigenfrau');
        $fremde = $this->fremd('Fremdmann');
        $fremdeId = (int) $fremde->lernender->lernender_id;

        foreach (['trainer.dashboard', 'trainer.learners.index'] as $route) {
            $json = $this->actingAs($this->bb)->getJson(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('Eigenfrau', $json, $route);
            $this->assertStringNotContainsString('Fremdmann', $json, $route.' JSON');

            $html = $this->actingAs($this->bb)->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('Eigenfrau', $html, $route);
            $this->assertStringNotContainsString('Fremdmann', $html, $route.' HTML');
            $this->assertStringNotContainsString('/learners/'.$fremdeId.'"', $html, $route.' Link');
            $this->assertStringNotContainsString('/learners/'.$fremdeId.'&quot;', $html, $route.' Link in data-ziele');
        }

        $this->actingAs($this->bb)->getJson(route('trainer.learners.show', $fremdeId))->assertNotFound();
        $this->actingAs($this->bb)->get(route('trainer.learners.show', $fremdeId))->assertNotFound();
        $this->assertNotNull($eigene);
    }

    #[Test]
    public function das_dashboard_zeigt_die_hantel_mit_filtern_und_dem_weg_ins_cockpit(): void
    {
        $a = $this->betreut('Hantelanna');
        $this->betreut('Hantelbert');

        $html = $this->actingAs($this->bb)->get(route('trainer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="np-hantel-personen"', $html);
        $this->assertStringContainsString('data-np-baustein="hantel"', $html);
        $this->assertStringContainsString('x-data="npFilter(', $html);
        foreach (['status', 'kategorie', 'hantel_sort'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html, $name);
        }
        $this->assertStringContainsString('/learners/'.$a->lernender->lernender_id.'&quot;', $html, 'Ziel im Cockpit');

        // Filter wirken, ungültige Werte fallen auf den Standard
        $this->actingAs($this->bb)->get(route('trainer.dashboard', ['status' => 'kritisch', 'hantel_sort' => 'name']))->assertOk()
            ->assertSee('id="np-hantel-personen"', false);
        $json = $this->actingAs($this->bb)->getJson(route('trainer.dashboard', ['status' => 'x', 'kategorie' => '99999', 'hantel_sort' => 'x']))->assertOk()->json();
        $this->assertSame(['status' => 'alle', 'kategorie' => null, 'hantel_sort' => 'delta'], $json['filter']);
    }

    #[Test]
    public function tabellensortierung_und_hantelsortierung_haben_eigene_parameter(): void
    {
        $this->betreut('Trennanna');
        $this->betreut('Trennbert');

        $html = $this->actingAs($this->bb)->get(route('trainer.dashboard', ['sort' => 'gesamt', 'dir' => 'desc', 'hantel_sort' => 'name']))->assertOk()->getContent();

        // Das Hantel-Select zeigt seine eigene Sortierung, die Tabellensortierung bleibt als verstecktes Feld im Formular
        $this->assertMatchesRegularExpression('/<select name="hantel_sort" id="hantel-sort"[^>]*>.*?<option value="name" selected/s', $html);
        $this->assertStringContainsString('<input type="hidden" name="sort" value="gesamt">', $html);
        $this->assertStringContainsString('<input type="hidden" name="dir" value="desc">', $html);
        // Die Spaltenköpfe werden mit dem Filter neu geladen und tragen die Hantelfilter weiter
        $this->assertStringContainsString('<thead data-np-sortkoepfe>', $html);
        $this->assertMatchesRegularExpression('/aria-sort="descending"><a href="[^"]*hantel_sort=name[^"]*"/', $html);

        // Eine Tabellensortierung allein lässt die Hantel beim Standard «Veränderung»
        $html = $this->actingAs($this->bb)->get(route('trainer.dashboard', ['sort' => 'status']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select name="hantel_sort" id="hantel-sort"[^>]*>.*?<option value="delta" selected/s', $html);
    }

    #[Test]
    public function die_hantel_sortiert_nach_veraenderung_oder_name_und_filtert_eine_kategorie(): void
    {
        $fach = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $vorher = Semester::factory()->create(['start_datum' => now()->subMonths(14)->toDateString(), 'end_datum' => now()->subMonths(8)->toDateString(), 'sortierung' => 1]);
        $jetzt = Semester::factory()->create(['start_datum' => now()->subMonths(2)->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 2]);
        foreach ([['Aufsteiger', 3.0, 5.0], ['Absteiger', 5.0, 3.0]] as [$name, $alt, $neu]) {
            $user = User::factory()->lernender()->create(['nachname' => $name]);
            Betreuung::factory()->create(['berufsbildner_id' => $this->bb->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id]);
            foreach ([[$vorher, $alt], [$jetzt, $neu]] as [$s, $wert]) {
                Note::factory()->create(['lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $fach, 'semester_id' => $s->semester_id,
                    'pruefungsdatum' => $s->start_datum, 'note_wert' => $wert]);
            }
        }

        $zeilen = fn (array $query) => $this->actingAs($this->bb)->getJson(route('trainer.dashboard', $query))->assertOk()->json('diagramm.hantel.zeilen');

        $standard = $zeilen([]);
        $this->assertEquals([-2, 2], array_column($standard, 'delta'), 'grösster Rückgang zuerst');
        $this->assertStringEndsWith('Absteiger', $standard[0]['fach']);

        $this->assertStringEndsWith('Absteiger', $zeilen(['hantel_sort' => 'name'])[0]['fach']);
        $this->assertStringEndsWith('Aufsteiger', $zeilen(['hantel_sort' => 'name'])[1]['fach']);

        $kategorie = $zeilen(['kategorie' => $fach]);
        $this->assertSame([$fach, $fach], array_column($kategorie, 'kategorie_id'));
        $this->assertEquals([3, 5], array_column($kategorie, 'jetzt'));
    }

    #[Test]
    public function ohne_kritische_personen_bleibt_die_hantel_mit_leerzustand_im_dom(): void
    {
        $this->betreut('Gutefrau', 5.5);

        $html = $this->actingAs($this->bb)->get(route('trainer.dashboard', ['status' => 'kritisch']))->assertOk()->getContent();

        $this->assertStringContainsString('id="np-hantel-personen"', $html);
        $this->assertStringContainsString('data-np-unter-genuegend', $html);
        $this->assertStringContainsString('Keine Lernenden für diesen Filter.', $html);
        $this->assertStringNotContainsString('0 besser, 0 schlechter', $html);
    }

    #[Test]
    #[DataProvider('rollen')]
    public function die_lernendenliste_traegt_punktstreifen_bullet_sparkline_und_filter(string $rolle): void
    {
        $user = $rolle === 'admin' ? User::factory()->admin()->create() : $this->bb;
        $this->betreut('Listenanna', 3.5);

        $html = $this->actingAs($user)->get(route($rolle.'.learners.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="np-punktstreifen"', $html);
        $this->assertStringContainsString('data-np-baustein="punktstreifen"', $html);
        $this->assertStringContainsString('data-np-liste', $html);
        $this->assertStringContainsString('x-data="npFilter(', $html);
        $this->assertStringContainsString('viewBox="0 0 100 8"', $html, 'Mini-Bullet je Zeile');
        $this->assertStringContainsString('name="kategorie"', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]*grades\/export"[^>]* data-behalte-filter/', $html, 'Export behält den Filter');
    }

    /** @return array<string, array{string}> */
    public static function rollen(): array
    {
        return ['Berufsbildner' => ['trainer'], 'Admin' => ['admin']];
    }

    #[Test]
    public function die_lernendenliste_sortiert_nach_schnitt_und_veraenderung(): void
    {
        $this->betreut('Hochnote', 6.0);
        $this->betreut('Tiefnote', 2.0);

        foreach (['schnitt', 'delta'] as $sort) {
            $html = $this->actingAs($this->bb)->get(route('trainer.learners.index', ['sort' => $sort, 'dir' => 'desc']))->assertOk()->getContent();
            $this->assertStringContainsString('Hochnote', $html, $sort);
        }

        // Der Punktstreifen davor steht nach Schnitt aufsteigend: verglichen wird nur die Tabelle
        $tabelle = substr($html = $this->actingAs($this->bb)->get(route('trainer.learners.index', ['sort' => 'schnitt', 'dir' => 'desc']))->getContent(), (int) strpos($html, 'data-np-liste>'));
        $this->assertLessThan(strpos($tabelle, 'Tiefnote'), strpos($tabelle, 'Hochnote'));
    }

    #[Test]
    public function der_punktstreifen_nennt_ohne_noten_einen_leerzustand(): void
    {
        $user = User::factory()->lernender()->create(['nachname' => 'Ohnenote']);
        Betreuung::factory()->create(['berufsbildner_id' => $this->bb->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id]);

        $this->actingAs($this->bb)->getJson(route('trainer.learners.index'))->assertOk()->assertJsonPath('diagramm.punktstreifen.punkte', []);
        $this->actingAs($this->bb)->get(route('trainer.learners.index'))->assertOk()
            ->assertSee('data-np-baustein="punktstreifen"', false)
            ->assertSee(__('Keine Personen mit Note.'));
    }

    #[Test]
    public function das_cockpit_traegt_den_verlauf_mit_filtern_nur_mit_noten(): void
    {
        $mit = $this->betreut('Verlaufsanna');

        $html = $this->actingAs($this->bb)->get(route('trainer.learners.show', $mit->lernender->lernender_id))->assertOk()->getContent();
        $this->assertStringContainsString('npVerlauf(', $html);
        $this->assertStringContainsString('data-np-verlauf-chart', $html);
        foreach (['zeitraum', 'kategorie', 'fach', 'achse'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html, $name);
        }

        $ohne = User::factory()->lernender()->create(['nachname' => 'Verlaufslos']);
        Betreuung::factory()->create(['berufsbildner_id' => $this->bb->berufsbildner->berufsbildner_id, 'lernender_id' => $ohne->lernender->lernender_id]);
        $html = $this->actingAs($this->bb)->get(route('trainer.learners.show', $ohne->lernender->lernender_id))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-np-verlauf-chart', $html);
    }
}
