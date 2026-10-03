<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use App\Support\Einrichtung;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Alle Statistikseiten antworten auf derselben GET-Route mit HTML oder (Accept: application/json) mit dem Paket
 * {filter, diagramm, tabelle, zusammenfassung, meta}; unbekannte Filterwerte fallen auf den Standard (docs/auftrag/GUI-R6.md §6).
 */
class StatistikEndpunkteTest extends TestCase
{
    private User $lernender;

    private User $berufsbildner;

    private User $admin;

    private int $kategorieId;

    protected function setUp(): void
    {
        parent::setUp();

        // Der Admin-Einstieg leitet sonst in den Einrichtungsassistenten
        Einrichtung::abschliessen();

        $this->kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $this->kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);

        $start = Carbon::parse('2020-08-01');
        $semester = [];
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $semester[] = Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]);
            $start = $ende->copy()->addDay();
        }
        // das laufende Semester gehört zur Agenda (Prüfungen planen)
        Semester::factory()->create();

        $this->berufsbildner = User::factory()->berufsbildner()->create();
        $this->admin = User::factory()->admin()->create();
        $this->lernender = User::factory()->lernender([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths(18)->toDateString(),
            'lehrende' => now()->addMonths(30)->toDateString(),
        ])->create();
        Betreuung::factory()->create(['berufsbildner_id' => $this->berufsbildner->berufsbildner->berufsbildner_id, 'lernender_id' => $this->lernender->lernender->lernender_id]);

        foreach ([[$semester[0], 4.0], [$semester[1], 5.0]] as [$s, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $this->lernender->lernender->lernender_id,
                'kategorie_id' => $this->kategorieId,
                'semester_id' => $s->semester_id,
                'fach_id' => $fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($s->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $this->lernender->benutzer_id,
            ]);
        }

        // eine geplante Prüfung in zwei Wochen für die Agenda
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id')]);
        $this->actingAs($this->lernender)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modul->modul_id, 'datum' => now()->addWeeks(2)->toDateString(), 'gewichtung_prozent' => 40, 'titel' => 'LB2',
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function routen(): array
    {
        return [
            'learner.dashboard' => ['lernender', 'learner.dashboard', ['verlauf', 'wostehe']],
            'learner.grades.index' => ['lernender', 'learner.grades.index', ['hantel']],
            'learner.qualification.index' => ['lernender', 'learner.qualification.index', ['positionen']],
            'learner.exams.index' => ['lernender', 'learner.exams.index', ['zeitleiste']],
            'trainer.dashboard' => ['berufsbildner', 'trainer.dashboard', ['hantel']],
            'trainer.learners.index' => ['berufsbildner', 'trainer.learners.index', ['punktstreifen']],
            'trainer.learners.show' => ['berufsbildner', 'trainer.learners.show', ['verlauf']],
            'admin.dashboard' => ['admin', 'admin.dashboard', ['erfassung']],
            'admin.learners.index' => ['admin', 'admin.learners.index', ['punktstreifen']],
            'admin.reports.grades' => ['admin', 'admin.reports.grades', ['histogramm', 'lehrjahre']],
        ];
    }

    private function url(string $route, array $query = []): string
    {
        $parameter = str_ends_with($route, 'learners.show') ? ['lernender_id' => $this->lernender->lernender->lernender_id] : [];

        return route($route, $parameter + $query);
    }

    private function als(string $rolle): User
    {
        return $this->{$rolle};
    }

    #[Test]
    #[DataProvider('routen')]
    public function json_liefert_paket_mit_vary_und_no_store(string $rolle, string $route, array $schluessel): void
    {
        $antwort = $this->actingAs($this->als($rolle))->getJson($this->url($route));

        $antwort->assertOk()->assertJsonStructure(['filter', 'diagramm', 'tabelle', 'zusammenfassung', 'meta']);
        foreach ($schluessel as $s) {
            $antwort->assertJsonStructure([$s => []], $antwort->json('diagramm'));
            $this->assertNotNull($antwort->json("zusammenfassung.{$s}"), "{$route}: Zusammenfassung {$s}");
            $this->assertIsString($antwort->json("zusammenfassung.{$s}"));
            $this->assertArrayHasKey('spalten', $antwort->json("tabelle.{$s}"));
            $this->assertArrayHasKey('zeilen', $antwort->json("tabelle.{$s}"));
        }
        $this->assertStringContainsString('Accept', (string) $antwort->headers->get('Vary'));
        $this->assertStringContainsString('no-store', (string) $antwort->headers->get('Cache-Control'));
    }

    #[Test]
    #[DataProvider('routen')]
    public function html_traegt_vary_accept_und_die_statistikdaten(string $rolle, string $route, array $schluessel): void
    {
        $antwort = $this->actingAs($this->als($rolle))->get($this->url($route));

        $antwort->assertOk();
        $this->assertStringContainsString('Accept', (string) $antwort->headers->get('Vary'));
        $antwort->assertViewHas('statistik', fn (array $s) => array_keys($s['diagramm']) === $schluessel);
    }

    #[Test]
    #[DataProvider('routen')]
    public function unbekannte_filterwerte_fallen_auf_den_standard(string $rolle, string $route, array $schluessel): void
    {
        $abfrage = ['zeitraum' => 'xyz', 'kategorie' => 'abc', 'fach' => '999999', 'achse' => '??', 'semester_id' => 'x', 'sort' => 'quer', 'status' => 'foo', 'lehrjahr' => '77'];

        $this->actingAs($this->als($rolle))->getJson($this->url($route, $abfrage))
            ->assertOk()->assertJsonStructure(['filter', 'diagramm', 'tabelle', 'zusammenfassung', 'meta']);
    }

    #[Test]
    public function standardwerte_stehen_im_filter(): void
    {
        $this->actingAs($this->lernender)->getJson(route('learner.dashboard', ['zeitraum' => 'xyz', 'achse' => 'nein', 'kategorie' => '999999']))
            ->assertOk()
            ->assertJsonPath('filter.zeitraum', 'alles')
            ->assertJsonPath('filter.achse', 'datum')
            ->assertJsonPath('filter.kategorie', null)
            ->assertJsonPath('filter.modus', 'semester');

        $this->actingAs($this->lernender)->getJson(route('learner.exams.index', ['zeitraum' => 'xyz']))
            ->assertOk()->assertJsonPath('filter.zeitraum', '8w');

        $this->actingAs($this->admin)->getJson(route('admin.dashboard', ['zeitraum' => 'xyz']))
            ->assertOk()->assertJsonPath('filter.zeitraum', '12w')->assertJsonPath('diagramm.erfassung.wochen', 12);
    }

    #[Test]
    public function gueltige_filterwerte_wirken(): void
    {
        $antwort = $this->actingAs($this->lernender)->getJson(route('learner.dashboard', ['achse' => 'semester', 'kategorie' => $this->kategorieId, 'zeitraum' => 'lehrjahr']))
            ->assertOk()
            ->assertJsonPath('filter.achse', 'semester')
            ->assertJsonPath('filter.kategorie', $this->kategorieId)
            ->assertJsonPath('diagramm.verlauf.achse', 'semester');
        $this->assertEquals([4.0, 5.0], $antwort->json('diagramm.verlauf.serien.0.werte'));

        $this->actingAs($this->admin)->getJson(route('admin.dashboard', ['zeitraum' => '26w']))
            ->assertOk()->assertJsonPath('diagramm.erfassung.wochen', 26);

        $this->actingAs($this->lernender)->getJson(route('learner.exams.index', ['zeitraum' => '4w']))
            ->assertOk()->assertJsonPath('filter.zeitraum', '4w')->assertJsonCount(1, 'diagramm.zeitleiste.zeilen')
            ->assertJsonPath('diagramm.zeitleiste.zeilen.0.titel', 'LB2');
    }

    #[Test]
    public function semesterachse_ohne_filter_zeigt_gesamtschnitt_und_je_kategorie_eine_reihe(): void
    {
        $verlauf = $this->actingAs($this->lernender)->getJson(route('learner.dashboard', ['achse' => 'semester']))->assertOk()->json('diagramm.verlauf');

        // Gesamtschnitt hervorgehoben, dazu die Kategorie mit Noten; Kategorien ohne Note erscheinen nicht
        $this->assertSame(['Gesamtschnitt', 'Fachunterricht'], array_column($verlauf['serien'], 'name'));
        $this->assertTrue($verlauf['serien'][0]['dick']);
        $this->assertArrayNotHasKey('dick', $verlauf['serien'][1]);
        $this->assertEquals([4.0, 5.0], $verlauf['serien'][1]['werte']);
        $this->assertCount(count($verlauf['labels']), $verlauf['serien'][1]['werte']);

        $tabelle = $this->actingAs($this->lernender)->getJson(route('learner.dashboard', ['achse' => 'semester']))->json('tabelle.verlauf');
        $this->assertSame(['Semester', 'Gesamtschnitt', 'Fachunterricht'], $tabelle['spalten']);
        $this->assertSame('4.0', $tabelle['zeilen'][0][2]);

        // mit Kategorie bleibt es eine Reihe, die Tabelle hat zwei Spalten
        $mitKategorie = $this->actingAs($this->lernender)->getJson(route('learner.dashboard', ['achse' => 'semester', 'kategorie' => $this->kategorieId]))->json();
        $this->assertCount(1, $mitKategorie['diagramm']['verlauf']['serien']);
        $this->assertSame(['Semester', 'Note'], $mitKategorie['tabelle']['verlauf']['spalten']);

        // die Rollenweiche /dashboard nimmt die Filter mit
        $this->actingAs($this->lernender)->get(route('dashboard', ['achse' => 'semester']))
            ->assertRedirect(route('learner.dashboard', ['achse' => 'semester']));
    }

    #[Test]
    public function verlauf_der_datumsachse_hat_stichtage_punkte_und_die_genuegend_grenze(): void
    {
        $verlauf = $this->actingAs($this->lernender)->getJson(route('learner.dashboard'))->assertOk()->json('diagramm.verlauf');

        $this->assertSame('datum', $verlauf['achse']);
        $this->assertCount(count($verlauf['labels']), $verlauf['serien'][0]['werte']);
        $this->assertCount(2, $verlauf['punkte']);
        $this->assertSame(4.0, (float) $verlauf['grenze']);
        // laufender Schnitt aller Prüfungen bis heute: (4.0 + 5.0) / 2
        $this->assertEqualsWithDelta(4.5, (float) end($verlauf['serien'][0]['werte']), 1e-9);
    }

    #[Test]
    public function bericht_filter_gelten_im_html_serverseitig(): void
    {
        $fremderBeruf = Lehrberuf::factory()->create();
        User::factory()->lernender(['lehrberuf_id' => $fremderBeruf->lehrberuf_id])->create(['nachname' => 'Zebraxyz']);
        User::factory()->lernender(['lehrberuf_id' => $this->lernender->lernender->lehrberuf_id])->create(['nachname' => 'Giraffeabc']);

        $this->actingAs($this->admin)->get(route('admin.reports.grades', ['lehrberuf_id' => $fremderBeruf->lehrberuf_id, 'semester' => 'alle']))
            ->assertOk()->assertSee('Zebraxyz')->assertDontSee('Giraffeabc');

        // Lehrjahr, Kategorie und Zeitraum der Statistiken auch im JSON-Paket
        $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['lehrjahr' => '2', 'kategorie' => $this->kategorieId]))
            ->assertOk()->assertJsonPath('filter.lehrjahr', 2)->assertJsonPath('filter.kategorie', $this->kategorieId);
    }

    #[Test]
    public function bericht_html_filtert_die_tabelle_serverseitig_nach_lehrjahr(): void
    {
        $beruf = $this->lernender->lernender->lehrberuf_id;
        User::factory()->lernender(['lehrberuf_id' => $beruf, 'lehrbeginn' => now()->subDays(20)->toDateString(), 'lehrende' => now()->addYears(3)->toDateString()])
            ->create(['nachname' => 'Erstjahrzebra']);
        User::factory()->lernender(['lehrberuf_id' => $beruf, 'lehrbeginn' => now()->subMonths(15)->toDateString(), 'lehrende' => now()->addMonths(33)->toDateString()])
            ->create(['nachname' => 'Zweitjahrgiraffe']);

        $this->actingAs($this->admin)->get(route('admin.reports.grades', ['lehrjahr' => 2, 'semester' => 'alle']))
            ->assertOk()->assertSee('Zweitjahrgiraffe')->assertDontSee('Erstjahrzebra');

        $this->actingAs($this->admin)->get(route('admin.reports.grades', ['lehrjahr' => 1, 'semester' => 'alle']))
            ->assertOk()->assertSee('Erstjahrzebra')->assertDontSee('Zweitjahrgiraffe');

        // unbekanntes Lehrjahr fällt auf «alle»
        $this->actingAs($this->admin)->get(route('admin.reports.grades', ['lehrjahr' => 77, 'semester' => 'alle']))
            ->assertOk()->assertSee('Erstjahrzebra')->assertSee('Zweitjahrgiraffe');
    }

    #[Test]
    public function dashboard_html_mit_zeitraum_lehrjahr_traegt_die_gefilterte_verlaufstabelle(): void
    {
        // eine Prüfung im laufenden Lehrjahr (Beginn vor 18 Monaten, Lehrjahr 2 ab vor 6 Monaten); die zwei Noten aus setUp liegen davor
        $aktuell = Semester::query()->where('start_datum', '<=', now()->toDateString())->where('end_datum', '>=', now()->toDateString())->firstOrFail();
        $fachId = (int) DB::table('noten')->where('lernender_id', $this->lernender->lernender->lernender_id)->value('fach_id');
        $datum = now()->subMonth();
        DB::table('noten')->insert([
            'lernender_id' => $this->lernender->lernender->lernender_id,
            'kategorie_id' => $this->kategorieId,
            'semester_id' => $aktuell->semester_id,
            'fach_id' => $fachId,
            'modul_belegung_id' => null,
            'titel' => 'Aktuelle Note',
            'pruefungsdatum' => $datum->toDateString(),
            'note_wert' => 6.0,
            'gewichtung_prozent' => 100,
            'erfasst_von_benutzer_id' => $this->lernender->benutzer_id,
        ]);

        $zeilen = fn (string $zeitraum) => $this->actingAs($this->lernender)->get(route('learner.dashboard', ['zeitraum' => $zeitraum]))
            ->assertOk()->viewData('statistik')['tabelle']['verlauf']['zeilen'];

        $lehrjahr = $zeilen('lehrjahr');
        $this->assertCount(1, $lehrjahr);
        $this->assertSame($datum->format('d.m.Y'), $lehrjahr[0][0]);
        $this->assertCount(3, $zeilen('alles'));

        $this->actingAs($this->lernender)->get(route('learner.dashboard', ['zeitraum' => 'lehrjahr']))
            ->assertViewHas('statistik', fn (array $s) => $s['filter']['zeitraum'] === 'lehrjahr' && count($s['tabelle']['verlauf']['zeilen']) === 1);
    }

    #[Test]
    public function unbekannter_lehrberuf_im_bericht_fuellt_nicht_still_die_ganze_seite_leer(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['lehrberuf_id' => 999999, 'berufsbildner_id' => 'x', 'semester' => 'nie']))
            ->assertOk()->assertJsonPath('filter.lehrberuf_id', null)->assertJsonPath('filter.berufsbildner_id', null);
    }

    /** @return array<string, mixed> Paket in der Form von StatistikAntwort::paket mit einem Teil */
    private function teil(string $schluessel, array $diagramm, array $tabelle, string $satz, string $titel): array
    {
        return [
            'filter' => [],
            'diagramm' => [$schluessel => $diagramm],
            'tabelle' => [$schluessel => $tabelle],
            'zusammenfassung' => [$schluessel => $satz],
            'meta' => [$schluessel => ['titel' => $titel]],
        ];
    }

    #[Test]
    public function hantel_zeichnet_ring_punkt_und_veraenderung_als_text(): void
    {
        $paket = $this->teil('hantel', [
            'semester' => '2. Semester', 'vorsemester' => '1. Semester', 'grenze' => 4.0,
            'zeilen' => [
                ['schluessel' => 'a', 'fach' => 'Mathematik', 'vorher' => 3.5, 'jetzt' => 4.5, 'delta' => 1.0],
                ['schluessel' => 'b', 'fach' => 'Englisch', 'vorher' => null, 'jetzt' => 5.0, 'delta' => null],
            ],
        ], ['spalten' => ['Fach', 'Vorsemester', 'Aktuell', 'Veränderung'], 'zeilen' => [['Mathematik', '3.5', '4.5', '+1.00']]], '1 besser, 0 schlechter als im Vorsemester.', 'Vorsemester gegen aktuell');

        $html = $this->blade('<x-hantel :paket="$paket" id="np-test-hantel" />', ['paket' => $paket])->__toString();

        $this->assertStringContainsString('id="np-test-hantel"', $html);
        $this->assertStringContainsString('data-np-baustein', $html);
        $this->assertStringContainsString('Mathematik', $html);
        $this->assertStringContainsString('+1.0', $html);
        $this->assertStringContainsString('1 besser, 0 schlechter', $html);
        $this->assertStringContainsString('data-np-ansage', $html);
        $this->assertStringContainsString('Als Tabelle', $html);
    }

    #[Test]
    public function punktstreifen_nennt_namen_im_titel_und_den_median_erst_ab_drei_personen(): void
    {
        $punkt = fn (int $i, string $name, float $note) => ['id' => $i, 'name' => $name, 'gesamt' => $note, 'status' => 'gruen'];
        $tabelle = ['spalten' => ['Name', 'Gesamtschnitt', 'Status'], 'zeilen' => []];

        $zwei = $this->teil('punktstreifen', ['punkte' => [$punkt(1, 'Anna Muster', 4.2), $punkt(2, 'Ben Probe', 5.1)], 'median' => null, 'n' => 2, 'grenze' => 4.0], $tabelle, '2 Personen.', 'Wer braucht Aufmerksamkeit');
        $html = $this->blade('<x-punktstreifen :paket="$paket" />', ['paket' => $zwei])->__toString();
        $this->assertStringContainsString('<title>Anna Muster, 4.2</title>', $html);
        $this->assertStringNotContainsString('Median', $html);

        $drei = $this->teil('punktstreifen', ['punkte' => [$punkt(1, 'Anna Muster', 4.2), $punkt(2, 'Ben Probe', 5.1), $punkt(3, 'Cleo Test', 4.8)], 'median' => 4.8, 'n' => 3, 'grenze' => 4.0], $tabelle, '3 Personen, Median 4.8.', 'Wer braucht Aufmerksamkeit');
        $html = $this->blade('<x-punktstreifen :paket="$paket" />', ['paket' => $drei])->__toString();
        $this->assertStringContainsString('Median 4.8', $html);
    }

    #[Test]
    public function zeitleiste_und_verlauf_rendern_mit_text_statt_nur_farbe(): void
    {
        $zeitleiste = $this->teil('zeitleiste', [
            'von' => '2026-10-03', 'bis' => '2026-11-28', 'grenze' => 4.0,
            'zeilen' => [['titel' => 'Mathematik', 'datum' => '2026-10-20', 'status' => 'benoetigt', 'note' => 4.5, 'text' => 'benötigt 4.5']],
        ], ['spalten' => ['Datum', 'Prüfung', 'Nötig'], 'zeilen' => [['20.10.2026', 'Mathematik', 'benötigt 4.5']]], 'Nächste Prüfung am 20.10.2026, nötig sind 4.5.', 'Nächste Prüfungen');
        $html = $this->blade('<x-zeitleiste :paket="$paket" />', ['paket' => $zeitleiste])->__toString();
        $this->assertStringContainsString('benötigt 4.5', $html);
        $this->assertStringContainsString('20.10.2026', $html);

        $verlauf = $this->teil('verlauf', ['achse' => 'semester', 'labels' => ['1. Semester'], 'serien' => [['name' => 'Gesamtschnitt', 'werte' => [4.5], 'dick' => true]], 'grenze' => 4.0, 'punkte' => []],
            ['spalten' => ['Semester', 'Note'], 'zeilen' => [['1. Semester', '4.5']]], 'Schnitt von 4.5 auf 4.5 über 1 Semester.', 'Notenverlauf');
        $verlauf['meta']['verlauf']['ohneDatum'] = [['titel' => 'IPA', 'wert' => 5.0]];
        $html = $this->blade('<x-verlauf :paket="$paket" />', ['paket' => $verlauf])->__toString();
        $this->assertStringContainsString('x-data="npVerlauf(', $html);
        $this->assertStringContainsString('@np-statistik.window', $html);
        $this->assertStringContainsString('IPA', $html);
        $this->assertStringContainsString('data-np-tabelle', $html);
    }

    #[Test]
    public function filterleiste_mit_statistik_uebernimmt_npfilter_und_ohne_bleibt_sie_ein_get_formular(): void
    {
        $mit = $this->blade('<x-filterleiste action="/x" :statistik="[\'marken\' => true]"></x-filterleiste>')->__toString();
        $this->assertStringContainsString('x-data="npFilter(', $mit);
        $this->assertStringContainsString('np-filter-token', $mit);

        $ohne = $this->blade('<x-filterleiste action="/x"></x-filterleiste>')->__toString();
        $this->assertStringNotContainsString('npFilter', $ohne);
        $this->assertStringContainsString('method="GET"', $ohne);
    }

    #[Test]
    public function bericht_seite_traegt_npfilter_und_gibt_dem_export_den_filter_weiter(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.grades'))
            ->assertOk()->assertSee('npFilter(', false)->assertSee('data-behalte-filter', false);
    }
}
