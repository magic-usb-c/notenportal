<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Lehrberuf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stammdaten-Tabellen mit gekürzten Zellen: Wo ein Text mit truncate abgeschnitten wird, steht der volle Wortlaut
 * im title; im Notenbaum bekommt das Namensfeld genug Mindestbreite für den längsten üblichen Namen.
 */
class StammdatenKuerzungTest extends TestCase
{
    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR);

        return new \DOMXPath($dom);
    }

    #[Test]
    public function notenbaum_namensfeld_hat_platz_fuer_schlusspruefung_allgemeinbildung(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $this->actingAs($admin)->post(route('admin.master-data.grade-trees.template'), [
            'vorlage' => 'informatiker-efz-bivo2020', 'lehrberuf_id' => $lehrberuf->lehrberuf_id,
        ]);
        $baum = (int) DB::table('notenbaeume')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->value('baum_id');

        $html = (string) $this->actingAs($admin)->get(route('admin.master-data.grade-trees.show', $baum))->assertOk()->getContent();
        $felder = $this->dom($html)->query('//table//tbody//input[substring(@id, string-length(@id) - 4) = "-name"]');

        $this->assertGreaterThan(0, $felder->length);
        foreach ($felder as $feld) {
            // 15rem = 240 px: «Schlussprüfung Allgemeinbildung» (rund 215 px in 13 px) samt Polster und Rahmen
            $this->assertContains('min-w-60', preg_split('/\s+/', trim($feld->getAttribute('class'))), 'Mindestbreite des Namensfelds');
        }
    }

    #[Test]
    public function kategorien_code_zelle_nennt_den_vollen_code_im_title(): void
    {
        DB::table('kategorien')->where('code', 'BMS')->update(['aktiv' => 1]);
        $html = (string) $this->actingAs(User::factory()->admin()->create())->get(route('admin.master-data.categories.index'))->assertOk()->getContent();
        $zellen = $this->dom($html)->query('//table//tbody//td[contains(concat(" ", normalize-space(@class), " "), " truncate ") and normalize-space(.) = "BMS"]');

        $this->assertGreaterThan(0, $zellen->length);
        foreach ($zellen as $zelle) {
            $this->assertSame('BMS', $zelle->getAttribute('title'));
        }
    }

    #[Test]
    public function faecher_name_und_kategorie_nennen_den_vollen_text_im_title(): void
    {
        $kategorie = (string) DB::table('kategorien')->orderBy('kategorie_id')->value('name');
        $fach = Fach::factory()->create(['name' => 'Wirtschaft und Gesellschaft Vertiefung', 'kategorie_id' => DB::table('kategorien')->orderBy('kategorie_id')->value('kategorie_id')]);

        $html = (string) $this->actingAs(User::factory()->admin()->create())->get(route('admin.master-data.subjects.index'))->assertOk()->getContent();
        $xpath = $this->dom($html);

        $name = $xpath->query('//table//tbody//a[normalize-space(.) = "'.$fach->name.'"]');
        $this->assertSame(1, $name->length);
        $this->assertSame($fach->name, $name->item(0)->getAttribute('title'));

        $zelle = $xpath->query('//table//tbody//tr[.//a[normalize-space(.) = "'.$fach->name.'"]]/td[contains(concat(" ", normalize-space(@class), " "), " truncate ") and not(.//a)]');
        $this->assertSame(1, $zelle->length);
        $this->assertSame($kategorie, $zelle->item(0)->getAttribute('title'));
    }
}
