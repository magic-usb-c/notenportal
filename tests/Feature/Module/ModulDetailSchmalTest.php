<?php

declare(strict_types=1);

namespace Tests\Feature\Module;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Modulseite in schmalen Fenstern (1280 und 1440 px): Hauptspalte und Unterlagen behalten ihre Mindestbreite,
 * die Unterlagen rutschen darunter, statt dass die Leistungsbeurteilung überlappt und der Upload-Titel gekürzt wird.
 */
class ModulDetailSchmalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    private function seite(): \DOMXPath
    {
        $id = DB::table('module')->insertGetId(['modul_nummer' => 'X001', 'titel' => 'Testmodul', 'ziel_gewicht_summe_default' => 100, 'aktiv' => 1]);
        $html = (string) $this->actingAs(User::factory()->admin()->create())->get(route('modules.show', $id))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    private function hatKlasse(string $klasse): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$klasse.' ")';
    }

    #[Test]
    public function hauptspalte_und_unterlagen_umbrechen_untereinander_statt_sich_zu_quetschen(): void
    {
        $xpath = $this->seite();
        $unterlagen = $xpath->query('//section[@aria-labelledby="modul-unterlagen"]/parent::div[ '.$this->hatKlasse('min-w-80').' ]');

        $this->assertSame(1, $unterlagen->length, 'Die Unterlagen haben eine Mindestbreite, die den Upload-Titel fasst');
        $raster = $unterlagen->item(0)->parentNode;
        $this->assertStringContainsString('flex-wrap', $raster->getAttribute('class'), 'Die Spalten umbrechen untereinander, wenn die Mindestbreiten nicht nebeneinander passen');
        $this->assertStringNotContainsString('grid-cols-12', $raster->getAttribute('class'));
        $this->assertGreaterThan(0, $xpath->query('./div[ '.$this->hatKlasse('min-w-128').' ]', $raster)->length, 'Die Hauptspalte hat Platz für die Tabelle der Leistungsbeurteilung');
    }
}
