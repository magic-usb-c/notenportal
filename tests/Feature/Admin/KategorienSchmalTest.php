<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kategorientabelle in schmalen Fenstern (1280 und 1440 px): Die festen Spalten lassen der Promotionsregel Platz,
 * und die Regel umbricht, statt in der Zelle abgeschnitten zu werden und über die Nachbarspalten zu laufen.
 */
class KategorienSchmalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    private function seite(): \DOMXPath
    {
        DB::table('kategorien')->where('code', 'BMS')->update([
            'promotion_min_schnitt' => 4.0, 'promotion_max_ungenuegend' => 2, 'promotion_max_minuspunkte' => 2,
        ]);
        $html = (string) $this->actingAs(User::factory()->admin()->create())->get(route('admin.master-data.categories.index'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    #[Test]
    public function promotionsregel_umbricht_statt_in_der_zelle_abgeschnitten_zu_werden(): void
    {
        $zellen = $this->seite()->query('//table//tbody//td[contains(normalize-space(.), "Schnitt ab")]');

        $this->assertGreaterThan(0, $zellen->length);
        foreach ($zellen as $zelle) {
            $klassen = ' '.$zelle->getAttribute('class').' ';
            $this->assertStringNotContainsString(' truncate ', $klassen, 'Die Regel wird nicht mit Auslassungspunkten gekürzt');
            $this->assertFalse($zelle->hasAttribute('title'), 'Ohne Kürzung braucht die Regel keinen Tooltip');
        }
    }

    #[Test]
    public function feste_spalten_lassen_bei_1280_px_platz_fuer_die_promotionsregel(): void
    {
        $breiten = 0;
        foreach ($this->seite()->query('//table//thead//th[@scope="col"]') as $th) {
            if (preg_match('~(?:^|\s)w-(\d+)(?:\s|$)~', $th->getAttribute('class'), $m)) {
                $breiten += (int) $m[1] * 4;
            }
        }

        // Karte bei 1280 px: 948 px, Tabelle 944 px. Die Promotionsspalte soll mindestens 100 px behalten.
        $this->assertGreaterThan(0, $breiten);
        $this->assertLessThanOrEqual(944 - 100, $breiten, 'Summe der festen Spaltenbreiten in px');
    }
}
