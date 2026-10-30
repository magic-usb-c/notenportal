<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Notenliste des Lernenden: Tabellenname, rechtsbündige Anzahl, Aktionen ausserhalb der summary. */
class NotenlisteBarrierefreiheitTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        DB::table('semester')->insert(['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->toDateString(), 'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf])->create();
        $this->actingAs($this->user);
        $this->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $modul, 'pruefungsdatum' => now()->subDays(3)->toDateString(),
            'note_wert' => '5.0', 'gewichtung_prozent' => 100, 'titel' => 'LB1',
        ])->assertSessionHasNoErrors();
    }

    private function seite(): DOMXPath
    {
        $html = $this->get(route('learner.grades.index'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($dom);
    }

    #[Test]
    public function pruefungsanzahl_steht_rechtsbuendig_in_der_zahlenspalte(): void
    {
        $zellen = $this->seite()->query('//table[contains(@class,"np-tabelle")]//td//span[contains(@class,"tabular-nums") and @aria-hidden="true"]');

        $this->assertGreaterThan(0, $zellen->length);
        foreach ($zellen as $zelle) {
            $this->assertStringContainsString('text-right', $zelle->getAttribute('class'));
        }
    }

    #[Test]
    public function jede_kategorietabelle_traegt_einen_namen(): void
    {
        $tabellen = $this->seite()->query('//table[contains(@class,"np-tabelle")]');

        $this->assertGreaterThan(0, $tabellen->length);
        foreach ($tabellen as $tabelle) {
            $dok = new DOMXPath($tabelle->ownerDocument);
            $caption = $dok->query('./caption', $tabelle)->item(0);
            $bezug = $tabelle->getAttribute('aria-labelledby');
            $name = $caption?->textContent ?? ($bezug !== '' ? $dok->query('//*[@id="'.$bezug.'"]')->item(0)?->textContent : null);
            $this->assertNotNull($name, 'Tabelle ohne Namen');
            $this->assertNotSame('', trim($name));
        }
    }

    #[Test]
    public function aktionen_der_pruefung_liegen_ausserhalb_der_summary(): void
    {
        $xp = $this->seite();

        $summaries = $xp->query('//details[contains(@class,"np-note-detail")]/summary');
        $this->assertGreaterThan(0, $summaries->length);
        $this->assertSame(0, $xp->query('//details[contains(@class,"np-note-detail")]/summary//a | //details[contains(@class,"np-note-detail")]/summary//button | //details[contains(@class,"np-note-detail")]/summary//form')->length);

        // Bearbeiten und Löschen bleiben je Prüfung erreichbar
        $this->assertSame(1, $xp->query('//a[contains(@aria-label,"Note bearbeiten: ")][@x-data]')->length);
        $this->assertSame(1, $xp->query('//form[contains(@action,"/grades/")][.//button[contains(@aria-label,"Note löschen")]]')->length);
    }
}
