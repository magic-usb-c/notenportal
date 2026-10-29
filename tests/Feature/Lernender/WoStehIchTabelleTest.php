<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Übersicht «Wo stehe ich»: Der Modus (Semester/Lehrzeit) ist ein Filter des Statistikpakets. Der Server rendert
 * genau eine Tabelle für den gewählten Modus; das Segment steht als Radio im Filterformular, ohne JS trägt die
 * Adresse den Modus, mit JS tauscht npFilter die Zeilen ohne Volllader.
 */
class WoStehIchTabelleTest extends TestCase
{
    private int $modulNeu;

    private int $modulAlt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $fach = DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id');
        $this->modulNeu = $this->modul($lehrberuf, $fach, '908', 'Testmodul Theta durchführen');
        $this->modulAlt = $this->modul($lehrberuf, $fach, '909', 'Testmodul Omega prüfen');
        DB::table('semester')->insert([
            ['bezeichnung' => 'T-1', 'start_datum' => now()->subMonths(8)->toDateString(), 'end_datum' => now()->subMonths(2)->toDateString(), 'sortierung' => 10],
            ['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->addDay()->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11],
        ]);
        Konfiguration::vergessen();
        $this->actingAs(User::factory()->lernender(['lehrberuf_id' => $lehrberuf])->create());
    }

    private function modul(int $lehrberuf, int $kategorie, string $nummer, string $titel): int
    {
        $id = DB::table('module')->insertGetId(['modul_nummer' => $nummer, 'titel' => $titel]);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $id, 'kategorie_id' => $kategorie]);

        return $id;
    }

    private function note(int $modul, string $wert, string $datum): void
    {
        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul, 'pruefungsdatum' => $datum,
            'note_wert' => $wert, 'gewichtung_prozent' => 100,
        ])->assertSessionHasNoErrors();
    }

    /** Eine Note im laufenden Semester (T-2), eine im vorigen (T-1): nur die Lehrzeit kennt beide. */
    private function zweiSemester(): void
    {
        $this->note($this->modulNeu, '5.0', now()->subDays(3)->toDateString());
        $this->note($this->modulAlt, '4.0', now()->subMonths(5)->toDateString());
    }

    /** Die Tabelle «Wo stehe ich» samt Inhalt – es gibt sie genau einmal. */
    private function tabelle(string $html): string
    {
        $anzahl = preg_match_all('/<table[^>]*>\s*<caption class="sr-only">'.preg_quote(__('Zeugnisnoten je Fach und Modul, schwächste zuerst'), '/').'<\/caption>.*?<\/table>/s', $html, $treffer);
        $this->assertSame(1, $anzahl, 'Genau eine Tabelle «Wo stehe ich»');

        return $treffer[0][0];
    }

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    #[Test]
    public function der_server_rendert_das_laufende_semester_mit_zeilenkoepfen(): void
    {
        $this->zweiSemester();

        $tabelle = $this->tabelle($this->get(route('learner.dashboard'))->assertOk()->getContent());

        $this->assertStringContainsString('Testmodul Theta durchführen', $tabelle);
        $this->assertStringNotContainsString('Testmodul Omega prüfen', $tabelle);
        $this->assertStringContainsString('<th scope="row"', $tabelle);
        $this->assertStringContainsString('>'.__('Fach / Modul').'<', $tabelle);
    }

    #[Test]
    public function die_adresse_schaltet_auf_die_lehrzeit_um(): void
    {
        $this->zweiSemester();

        $html = $this->get(route('learner.dashboard', ['modus' => 'lehrzeit']))->assertOk()->getContent();
        $tabelle = $this->tabelle($html);

        $this->assertStringContainsString('Testmodul Theta durchführen', $tabelle);
        $this->assertStringContainsString('Testmodul Omega prüfen', $tabelle);
        $this->assertSame('lehrzeit', $this->dom($html)->query('//input[@name="modus"][@checked]')->item(0)?->getAttribute('value'));
    }

    #[Test]
    public function das_modus_segment_steht_als_radio_im_filterformular(): void
    {
        $this->zweiSemester();

        $xpath = $this->dom($this->get(route('learner.dashboard'))->assertOk()->getContent());
        $formulare = $xpath->query('//form[.//input[@name="modus"]]');

        $this->assertSame(1, $formulare->length, 'Genau ein Formular trägt den Modus');
        $form = $formulare->item(0);
        $this->assertSame(route('learner.dashboard'), $form->getAttribute('action'));
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame(0, $xpath->query('.//form', $form)->length, 'Kein verschachteltes Formular');
        $this->assertSame(['semester', 'lehrzeit'], array_map(fn (\DOMElement $i) => $i->getAttribute('value'), iterator_to_array($xpath->query('.//input[@name="modus"]', $form))));
        $this->assertSame('semester', $xpath->query('.//input[@name="modus"][@checked]', $form)->item(0)?->getAttribute('value'), 'Ohne Adresse gilt das Semester');
        $this->assertNotNull($xpath->query('.//select[@name="zeitraum"]', $form)->item(0), 'Verlauf-Filter im selben Formular');
    }
}
