<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeitentitelTest extends TestCase
{
    #[Test]
    public function sonderzeichen_im_titel_werden_genau_einmal_maskiert(): void
    {
        // Prüferbefund: der Browsertab zeigte «Lehrberufe &amp;amp; Fächer» (Titel über zwei Slots gereicht)
        $this->seed(BasisSeeder::class);
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('admin.setup', 'professions'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>[^<]*Lehrberufe &amp; Fächer – /u', (string) $html);
        $this->assertStringNotContainsString('&amp;amp;', (string) $html);
    }

    #[Test]
    public function text_mit_entitaeten_oder_spitzen_klammern_erscheint_im_titel_wortgetreu(): void
    {
        // Prüferbefund: der Name «QV &amp; <b>BM</b>» stand im Tab als «QV & <b>BM</b>» – zweimal dekodiert
        $this->seed(BasisSeeder::class);
        $admin = User::factory()->admin()->create();
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'STT', 'name' => 'Seitentitel-Test EFZ']);
        $d = BaumVorlage::laden('informatiker-efz-bivo2020');
        $d['name'] = 'QV &amp; <b>BM</b>';
        $baum = app(BaumVorlage::class)->importieren($d, $lehrberuf);

        $html = (string) $this->actingAs($admin)->get(route('admin.master-data.grade-trees.show', $baum))->assertOk()->getContent();

        $this->assertStringContainsString('<title>QV &amp;amp; &lt;b&gt;BM&lt;/b&gt; – ', $html);
    }
}
