<?php

declare(strict_types=1);

namespace Tests\Feature\Module;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Modulseite als Quellliste mit Detail: links alle Module ohne Seitenumbruch, rechts das gewählte.
 * Ohne Auswahl öffnet das erste Modul (bei einer Suche der erste Treffer); Lernende sehen ihre
 * eigenen Module als erste Gruppe.
 */
class ModullisteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    private function modul(string $nummer, string $titel): int
    {
        return DB::table('module')->insertGetId(['modul_nummer' => $nummer, 'titel' => $titel, 'ziel_gewicht_summe_default' => 100, 'aktiv' => 1]);
    }

    #[Test]
    public function liste_zeigt_alle_module_und_oeffnet_ohne_auswahl_das_erste(): void
    {
        foreach (range(1, 60) as $i) {
            $this->modul(sprintf('X%03d', $i), "Testmodul {$i}");
        }
        $erstes = (int) DB::table('module')->where('modul_nummer', 'X001')->value('modul_id');

        $antwort = $this->actingAs(User::factory()->berufsbildner()->create())->get(route('modules.index'))->assertOk();

        $antwort->assertSee('Testmodul 60')->assertSee('id="modul-titel"', false);
        $this->assertSame($erstes, $antwort->viewData('modul')->modul_id);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('modules.show', $erstes), '#').'"\s+[^>]*aria-current="page"#', (string) $antwort->getContent());
    }

    #[Test]
    public function suche_oeffnet_den_ersten_treffer_und_fuellt_das_suchfeld(): void
    {
        $this->modul('A100', 'Netzwerke planen');
        $treffer = $this->modul('B200', 'Daten sichern');
        $this->modul('C300', 'Daten auswerten');

        $antwort = $this->actingAs(User::factory()->berufsbildner()->create())
            ->get(route('modules.index', ['suche' => '  daten   SICHERN ']))->assertOk();

        $this->assertSame($treffer, $antwort->viewData('modul')->modul_id);
        $this->assertSame('daten SICHERN', $antwort->viewData('suche'));
        // Die Liste bleibt vollständig – gefiltert wird im Browser
        $antwort->assertSee('Netzwerke planen');
    }

    #[Test]
    public function ohne_module_erscheint_der_leerzustand_mit_naechstem_schritt(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('modules.index'))
            ->assertOk()
            ->assertSee(__('Noch keine Module erfasst.'))
            ->assertSee(route('modules.create'));
    }

    #[Test]
    public function lernende_sehen_ihre_module_als_erste_gruppe(): void
    {
        $fremd = $this->modul('A100', 'Fremdes Modul');
        $eigen = $this->modul('Z900', 'Eigenes Modul');
        $lernender = User::factory()->lernender()->create();
        DB::table('modul_belegungen')->insert(['lernender_id' => $lernender->lernender->lernender_id, 'modul_id' => $eigen, 'start_datum' => '2026-08-01']);

        $antwort = $this->actingAs($lernender)->get(route('modules.show', $fremd))->assertOk();

        $antwort->assertSeeInOrder([__('Deine Module'), 'Eigenes Modul', __('Weitere Module'), 'Fremdes Modul']);
        $this->assertFalse($antwort->viewData('belegt'));
        $antwort->assertSee(__('Zu meinen Modulen hinzufügen'));

        // Berufsbildner haben keine eigenen Module: eine einzige Liste ohne Gruppen
        $this->actingAs(User::factory()->berufsbildner()->create())->get(route('modules.index'))
            ->assertOk()->assertDontSee(__('Deine Module'));
    }
}
