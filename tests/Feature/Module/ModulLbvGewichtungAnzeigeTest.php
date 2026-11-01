<?php

namespace Tests\Feature\Module;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulLbvGewichtungAnzeigeTest extends TestCase
{
    #[Test]
    public function lbv_gewichtung_zeigt_nachkommastellen_statt_abzuschneiden(): void
    {
        $lernender = User::factory()->lernender()->create();
        $modul = DB::table('module')->insertGetId(['modul_nummer' => '778', 'titel' => 'Gewichtung']);
        foreach ([['A', 37.5], ['B', 33.33], ['C', 29.17], ['D', 50]] as $i => [$bezeichnung, $gewicht]) {
            DB::table('modul_lbv_elemente')->insert(['modul_id' => $modul, 'bezeichnung' => $bezeichnung, 'gewichtung_prozent' => $gewicht, 'sortierung' => $i]);
        }

        $html = $this->actingAs($lernender)->get(route('modules.show', $modul))->assertOk()->getContent();

        preg_match_all('/tabular-nums text-text">([^<]*)</', $html, $treffer);
        $this->assertSame(['37.5 %', '33.33 %', '29.17 %', '50 %'], array_map('trim', $treffer[1]));
    }
}
