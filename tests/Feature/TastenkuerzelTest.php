<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TastenkuerzelTest extends TestCase
{
    #[Test]
    public function lernender_bekommt_die_eigenen_ziel_urls(): void
    {
        $user = User::factory()->lernender()->create();

        $ziele = $this->zieleAus($this->actingAs($user)->get(route('learner.dashboard')));

        $this->assertSame(route('learner.dashboard'), $ziele['ziele']['d']);
        $this->assertSame(route('learner.grades.index'), $ziele['ziele']['n']);
        $this->assertSame(route('learner.exams.index'), $ziele['ziele']['a']);
        $this->assertNull($ziele['ziele']['l']);
        $this->assertSame(route('profile.edit'), $ziele['ziele']['p']);
        $this->assertSame(route('learner.grades.create'), $ziele['neueNote']);
    }

    #[Test]
    public function berufsbildner_bekommt_die_eigenen_ziel_urls(): void
    {
        $user = User::factory()->berufsbildner()->create();

        $ziele = $this->zieleAus($this->actingAs($user)->get(route('trainer.dashboard')));

        $this->assertSame(route('trainer.dashboard'), $ziele['ziele']['d']);
        $this->assertNull($ziele['ziele']['n']);
        $this->assertSame(route('trainer.exams.index'), $ziele['ziele']['a']);
        $this->assertSame(route('trainer.learners.index'), $ziele['ziele']['l']);
        $this->assertSame(route('profile.edit'), $ziele['ziele']['p']);
        $this->assertNull($ziele['neueNote']);
    }

    #[Test]
    public function admin_bekommt_die_eigenen_ziel_urls(): void
    {
        $user = User::factory()->admin()->create();

        $ziele = $this->zieleAus($this->actingAs($user)->get(route('admin.dashboard')));

        $this->assertSame(route('admin.dashboard'), $ziele['ziele']['d']);
        $this->assertNull($ziele['ziele']['n']);
        $this->assertSame(route('admin.exams.index'), $ziele['ziele']['a']);
        $this->assertSame(route('admin.learners.index'), $ziele['ziele']['l']);
        $this->assertSame(route('profile.edit'), $ziele['ziele']['p']);
        $this->assertNull($ziele['neueNote']);
    }

    #[Test]
    public function gast_bekommt_keinen_dialog(): void
    {
        $this->get(route('login'))->assertDontSee('npTastenkuerzel', false);
    }

    /**
     * Illuminate\Support\Js::from() bettet die Konfiguration nicht als rohes JSON ein, sondern als
     * `JSON.parse('…')` mit \uXXXX-Escapes (sicher für ein HTML-Attribut) – hier wird das genauso
     * entschachtelt, wie es der Browser beim Parsen des JS-Strings tun würde.
     *
     * @return array{ziele: array<string, ?string>, neueNote: ?string}
     */
    private function zieleAus(TestResponse $antwort): array
    {
        $antwort->assertOk();
        $antwort->assertSee('npTastenkuerzel', false);

        preg_match('/npTastenkuerzel\(JSON\.parse\(\'(.*?)\'\)\)"/s', (string) $antwort->getContent(), $treffer);
        $this->assertNotEmpty($treffer, 'npTastenkuerzel-Konfiguration nicht im Markup gefunden.');

        $json = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})|\\\\(.)/', function (array $m) {
            return $m[1] !== '' ? mb_convert_encoding(pack('n', hexdec($m[1])), 'UTF-8', 'UTF-16BE') : $m[2];
        }, $treffer[1]);

        $daten = json_decode((string) $json, true);
        $this->assertIsArray($daten);

        return $daten;
    }
}
