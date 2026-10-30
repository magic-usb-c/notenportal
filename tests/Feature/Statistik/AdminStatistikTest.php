<?php

declare(strict_types=1);

namespace Tests\Feature\Statistik;

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
 * Admin-Statistiken (GUI-R6 §6): Erfassung je Woche (S9, nur Betriebssummen), Notenverteilung in Viertelnoten (S10)
 * und Lehrjahre im Vergleich (S11). Gelöschte Noten zählen nirgends, Median und Quartile erst ab drei Werten.
 */
class AdminStatistikTest extends TestCase
{
    private int $kategorieId;

    private Lehrberuf $lehrberuf;

    /** @var list<Semester> */
    private array $semester = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Einrichtung::abschliessen();

        $this->admin = User::factory()->admin()->create();
        $this->kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $this->lehrberuf = Lehrberuf::factory()->create();
        // Zeugnisnoten auf 0.1 gerundet, damit 3.8 in die Klasse 3.75 fällt und nicht auf 4.0 springt
        Kategorie::whereKey($this->kategorieId)->update(['rundung_element' => 0.1]);

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
    public function zeitraum_bestimmt_die_zahl_der_wochenwerte(): void
    {
        foreach (['12w' => 12, '26w' => 26, '52w' => 52] as $zeitraum => $wochen) {
            $antwort = $this->actingAs($this->admin)->getJson(route('admin.dashboard', ['zeitraum' => $zeitraum]))->assertOk();

            $this->assertCount($wochen, $antwort->json('diagramm.erfassung.werte'), $zeitraum);
            $this->assertCount($wochen, $antwort->json('diagramm.erfassung.labels'), $zeitraum);
            $this->assertSame($wochen, $antwort->json('diagramm.erfassung.wochen'), $zeitraum);
            $this->assertSame($zeitraum, $antwort->json('filter.zeitraum'));
        }
    }

    #[Test]
    public function ohne_oder_mit_unbekanntem_zeitraum_gelten_zwoelf_wochen(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.dashboard'))->assertOk()->assertJsonCount(12, 'diagramm.erfassung.werte');
        $this->actingAs($this->admin)->getJson(route('admin.dashboard', ['zeitraum' => '99w']))->assertOk()->assertJsonCount(12, 'diagramm.erfassung.werte');
    }

    #[Test]
    public function die_seite_zeichnet_den_gewaehlten_zeitraum_als_baustein_mit_median(): void
    {
        $this->lernender('Ammann', 4.0);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard', ['zeitraum' => '26w']))->assertOk();

        $html->assertSee('data-np-baustein="erfassung"', false);
        $html->assertSee('id="np-erfassung"', false);
        $this->assertMatchesRegularExpression('/name="zeitraum" value="26w"[^>]*\bchecked\b/', $html->getContent());
        $html->assertDontSee(__('Median'), false);
        $this->assertSame(26, substr_count($html->getContent(), 'title="KW '), 'je Woche eine Säule mit Titel');
    }

    #[Test]
    public function die_medianlinie_erscheint_erst_wenn_die_meisten_wochen_noten_haben(): void
    {
        foreach (range(0, 7) as $woche) {
            $this->lernender('Woche'.$woche, 4.5, erstellt: now()->subWeeks($woche));
        }

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $html->assertSee(__('Median').' 1', false);
    }

    #[Test]
    public function erfassung_zaehlt_geloeschte_noten_nicht_und_ordnet_nach_der_woche(): void
    {
        $this->lernender('Ammann', 4.0);
        $this->lernender('Brunner', 5.0);
        $this->lernender('Camenzind', 3.0, geloescht: true);
        $this->lernender('Dubach', 5.5, erstellt: now()->subWeeks(20));

        $zwoelf = $this->actingAs($this->admin)->getJson(route('admin.dashboard'))->json('diagramm.erfassung.werte');
        $sechsundzwanzig = $this->actingAs($this->admin)->getJson(route('admin.dashboard', ['zeitraum' => '26w']))->json('diagramm.erfassung.werte');

        $this->assertSame(2, array_sum($zwoelf), 'gelöscht und ausserhalb des Fensters zählt nicht');
        $this->assertSame(2, $zwoelf[11], 'die aktuelle Woche steht zuletzt');
        $this->assertSame(3, array_sum($sechsundzwanzig));
        $this->assertSame(1, $sechsundzwanzig[26 - 1 - 20]);
    }

    #[Test]
    public function erfassung_nennt_nur_betriebssummen_keine_personen(): void
    {
        $this->lernender('Ammann', 4.0);

        $antwort = $this->actingAs($this->admin)->getJson(route('admin.dashboard'))->assertOk();

        $this->assertStringNotContainsString('Ammann', (string) $antwort->getContent());
        $this->assertSame(['spalten', 'zeilen'], array_keys($antwort->json('tabelle.erfassung')));
        $this->assertSame([__('Woche'), __('Anzahl')], $antwort->json('tabelle.erfassung.spalten'));
    }

    #[Test]
    public function histogramm_klassen_summieren_zur_zahl_der_noten_ohne_geloeschte(): void
    {
        foreach ([['Ammann', 3.8], ['Brunner', 4.0], ['Camenzind', 5.75], ['Dubach', 6.0]] as [$name, $note]) {
            $this->lernender($name, $note);
        }
        $this->lernender('Egger', 2.0, geloescht: true);

        $antwort = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle']))->assertOk();
        $histogramm = $antwort->json('diagramm.histogramm');

        $this->assertCount(20, $histogramm['werte'], 'Viertelnoten von 1.00 bis 5.75');
        $this->assertSame(4, array_sum($histogramm['werte']));
        $this->assertSame(4, $histogramm['n']);
        $klassen = array_combine($histogramm['labels'], $histogramm['werte']);
        $this->assertSame(1, $klassen['3.75']);
        $this->assertSame(1, $klassen['4.00']);
        $this->assertSame(2, $klassen['5.75'], 'die letzte Klasse schliesst 6.0 ein');
    }

    #[Test]
    public function median_der_verteilung_ist_null_unter_drei_noten_und_steht_ab_drei(): void
    {
        $this->lernender('Ammann', 4.0);
        $this->lernender('Brunner', 5.0);

        $url = route('admin.reports.grades', ['semester' => 'alle']);
        $zwei = $this->actingAs($this->admin)->getJson($url)->assertOk();
        $this->assertNull($zwei->json('diagramm.histogramm.median'));
        $this->actingAs($this->admin)->get($url)->assertOk()->assertDontSee(__('Median').' 4', false);

        $this->lernender('Camenzind', 4.5);

        $drei = $this->actingAs($this->admin)->getJson($url)->assertOk();
        $this->assertSame(4.5, $drei->json('diagramm.histogramm.median'));
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSee(__('Median').' 4.50', false);
    }

    #[Test]
    public function median_der_lehrjahre_ist_null_unter_drei_personen_und_steht_ab_drei(): void
    {
        $this->lernender('Ammann', 4.0, monate: 6);
        $this->lernender('Brunner', 4.0);
        $this->lernender('Camenzind', 5.0);

        $url = route('admin.reports.grades', ['semester' => 'alle']);
        $zwei = $this->actingAs($this->admin)->getJson($url)->assertOk();
        $this->assertCount(2, $zwei->json('diagramm.lehrjahre.jahre'));
        foreach ($zwei->json('diagramm.lehrjahre.jahre') as $jahr) {
            $this->assertNull($jahr['median'], 'höchstens zwei Personen im Lehrjahr: kein Median');
        }
        $this->actingAs($this->admin)->get($url)->assertOk()->assertDontSee('class="fill-text"', false);

        $this->lernender('Dubach', 4.5);

        $drei = $this->actingAs($this->admin)->getJson($url)->assertOk();
        $zweitesJahr = collect($drei->json('diagramm.lehrjahre.jahre'))->firstWhere('jahr', 2);
        $this->assertSame(4.5, $zweitesJahr['median']);
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('class="fill-text"', false);
    }

    #[Test]
    public function die_seite_zeichnet_die_genuegend_linie_und_einen_streifen_je_lehrjahr(): void
    {
        $this->lernender('Ammann', 4.0);
        $this->lernender('Brunner', 5.0);
        $this->lernender('Camenzind', 3.5, monate: 6);

        $html = $this->actingAs($this->admin)->get(route('admin.reports.grades', ['semester' => 'alle']))->assertOk();

        $html->assertSee(__('genügend :wert', ['wert' => '4.0']));
        $html->assertSee(__(':jahr. Lehrjahr', ['jahr' => 1]).':', false);
        $this->assertSame(3, preg_match_all('/<circle cx="[\d.]+" cy="[\d.]+" r="4"/', $html->getContent()), 'ein Punkt je Person');
        $html->assertSee('name="kategorie"', false);
        $html->assertSee('name="lehrjahr"', false);
    }

    #[Test]
    public function lehrjahr_und_kategorie_filtern_beide_diagramme(): void
    {
        $this->lernender('Ammann', 4.0);
        $this->lernender('Brunner', 5.0);
        $this->lernender('Camenzind', 3.5, monate: 6);

        $zweites = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle', 'lehrjahr' => 2]))->assertOk();
        $this->assertSame(2, $zweites->json('diagramm.histogramm.n'));
        $this->assertSame([2], array_column($zweites->json('diagramm.lehrjahre.jahre'), 'jahr'));

        $kategorie = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle', 'kategorie' => $this->kategorieId]))->assertOk();
        $this->assertSame(3, $kategorie->json('diagramm.histogramm.n'));

        $unbekannt = $this->actingAs($this->admin)->getJson(route('admin.reports.grades', ['semester' => 'alle', 'lehrjahr' => 9]))->assertOk();
        $this->assertSame(3, $unbekannt->json('diagramm.histogramm.n'), 'unbekanntes Lehrjahr fällt auf alle');
    }

    #[Test]
    public function ohne_noten_im_zeitraum_steht_ein_leerzustand_mit_weg_zum_laengeren_zeitraum(): void
    {
        $this->lernender('Alt', 4.0, erstellt: now()->subWeeks(40));

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        $karte = $this->erfassungskarte($html);

        $this->assertStringNotContainsString('role="img"', $karte, 'kein Diagramm ohne Noten');
        $this->assertStringNotContainsString('<table', $karte, 'keine Nulltabelle');
        $this->assertStringContainsString(__('Keine Noten erfasst'), $karte);
        $this->assertStringContainsString('href="'.e(route('admin.dashboard', ['zeitraum' => '52w'])).'"', $karte);
        $this->assertStringContainsString('data-np-ansage', $karte, 'die Ansage bleibt für den Wechsel stehen');

        $laengster = $this->actingAs($this->admin)->get(route('admin.dashboard', ['zeitraum' => '52w']))->assertOk()->getContent();
        $this->assertStringContainsString('role="img"', $this->erfassungskarte($laengster), 'mit Noten wieder das Diagramm');
    }

    #[Test]
    public function im_laengsten_zeitraum_ohne_noten_gibt_es_keinen_weiterweg(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard', ['zeitraum' => '52w']))->assertOk()->getContent();
        $karte = $this->erfassungskarte($html);

        $this->assertStringContainsString(__('Keine Noten erfasst'), $karte);
        $this->assertStringNotContainsString('np-knopf', $karte);
    }

    #[Test]
    public function die_ansage_der_erfassung_ist_beim_laden_leer_und_traegt_den_satz_fuer_den_wechsel(): void
    {
        $this->lernender('Ammann', 4.0);

        $karte = $this->erfassungskarte($this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent());

        $this->assertMatchesRegularExpression('/<p class="sr-only" aria-live="polite" data-np-ansage data-np-text="[^"]+"><\/p>/', $karte, 'Live-Absatz beim Laden leer');
        $this->assertSame(1, substr_count($karte, 'role="img"'));
    }

    private function erfassungskarte(string $html): string
    {
        $von = strpos($html, 'id="np-erfassung"');
        $this->assertNotFalse($von, 'Erfassungskarte fehlt');

        return substr($html, $von, (int) strpos($html, '</section>', $von) - $von);
    }

    /**
     * Eine Person mit einer Fachnote. `monate` ist die Zeit seit Lehrbeginn (18 = zweites, 6 = erstes Lehrjahr).
     */
    private function lernender(string $nachname, float $wert, int $monate = 18, bool $geloescht = false, ?Carbon $erstellt = null): User
    {
        $user = User::factory()->lernender([
            'lehrberuf_id' => $this->lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths($monate)->toDateString(),
            'lehrende' => now()->addMonths(48 - $monate)->toDateString(),
        ])->create(['nachname' => $nachname]);

        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $this->kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        DB::table('noten')->insert([
            'lernender_id' => $user->lernender->lernender_id,
            'kategorie_id' => $this->kategorieId,
            'semester_id' => $this->semester[0]->semester_id,
            'fach_id' => $fach->fach_id,
            'modul_belegung_id' => null,
            'titel' => 'Note',
            'pruefungsdatum' => Carbon::parse($this->semester[0]->start_datum)->addDays(10)->toDateString(),
            'note_wert' => $wert,
            'gewichtung_prozent' => 100,
            'erfasst_von_benutzer_id' => $user->benutzer_id,
            'erstellt_am' => ($erstellt ?? now())->toDateTimeString(),
            'geloescht_am' => $geloescht ? now()->toDateTimeString() : null,
        ]);

        return $user;
    }
}
