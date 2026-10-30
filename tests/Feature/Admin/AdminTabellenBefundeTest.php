<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Befunde UI-admin-3, -4, -5, -7 und -10: Dashboard-Schwelle und -Titel, Tabellenbeschriftung, Entfernen-Sperre. */
class AdminTabellenBefundeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function ungesehenes_im_dashboard_wird_ohne_feste_schwelle_betont(): void
    {
        $bb = User::factory()->berufsbildner()->create(['vorname' => 'Berta', 'nachname' => 'Schwelle']);
        $lernender = User::factory()->lernender()->create();
        Betreuung::factory()->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender->lernender_id,
        ]);
        Note::factory()->count(3)->create(['lernender_id' => $lernender->lernender->lernender_id]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<td class="text-right font-semibold text-text">3</td>', $html);
        $this->assertStringNotContainsString('$bb->neu > 20', file_get_contents(resource_path('views/dashboards/admin.blade.php')));
    }

    #[Test]
    public function dashboard_nennt_tab_und_ueberschrift_gleich(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        preg_match('~<h1[^>]*>(.*?)</h1>~s', $html, $h1);
        $this->assertSame('Übersicht', trim(strip_tags($h1[1] ?? '')));
        $this->assertMatchesRegularExpression('~<title>Übersicht~u', $html);
    }

    /** @return array<string, array{string, array}> */
    public static function stammdatenSeiten(): array
    {
        return [
            'Lehrberufe' => ['admin.master-data.professions.index', []],
            'Fächer' => ['admin.master-data.subjects.index', []],
            'Kategorien' => ['admin.master-data.categories.index', []],
            'Semester' => ['admin.master-data.semesters.index', []],
            'Module' => ['admin.master-data.modules.index', []],
            'Module gruppiert' => ['admin.master-data.modules.index', ['gruppieren' => 'lernort']],
            'Notenbäume' => ['admin.master-data.grade-trees.index', []],
        ];
    }

    #[Test]
    #[DataProvider('stammdatenSeiten')]
    public function jede_stammdaten_tabelle_hat_eine_beschriftung(string $route, array $parameter): void
    {
        // Mindestens eine Zeile, damit die Tabelle und nicht der Leerzustand erscheint
        Modul::factory()->create();
        Fach::factory()->create();
        Semester::factory()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => Modul::factory()->create()->modul_id,
            'kategorie_id' => $this->kategorie('FACH'), 'pflicht' => 1, 'aktiv' => 1,
        ]);
        $this->actingAs($this->admin)->post(route('admin.master-data.grade-trees.template'), [
            'vorlage' => 'informatiker-efz-bivo2020', 'lehrberuf_id' => $lehrberuf->lehrberuf_id,
        ]);

        $html = $this->actingAs($this->admin)->get(route($route, $parameter))->assertOk()->getContent();

        $tabellen = substr_count($html, '<table');
        $this->assertGreaterThan(0, $tabellen, $route);
        $this->assertSame($tabellen, substr_count($html, '<caption class="sr-only">'), $route);
    }

    #[Test]
    public function lehrberuf_und_notenbaum_detail_haben_beschriftete_tabellen(): void
    {
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $modul = Modul::factory()->create();
        $fach = Fach::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => $this->kategorie('FACH'), 'pflicht' => 1, 'aktiv' => 1,
        ]);
        $this->actingAs($this->admin)->post(route('admin.master-data.professions.subjects.assign', $lehrberuf->lehrberuf_id), ['fach_id' => $fach->fach_id]);
        $this->actingAs($this->admin)->post(route('admin.master-data.grade-trees.template'), [
            'vorlage' => 'informatiker-efz-bivo2020', 'lehrberuf_id' => $lehrberuf->lehrberuf_id,
        ]);
        $baum = (int) DB::table('notenbaeume')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->value('baum_id');

        foreach ([
            route('admin.master-data.professions.show', $lehrberuf->lehrberuf_id),
            route('admin.master-data.grade-trees.show', $baum),
        ] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            $tabellen = substr_count($html, '<table');
            $this->assertGreaterThan(0, $tabellen, $url);
            $this->assertSame($tabellen, substr_count($html, '<caption class="sr-only">'), $url);
        }
    }

    #[Test]
    public function zugaenge_tabelle_hat_spaltenkoepfe_mit_scope_und_beschriftung(): void
    {
        $html = $this->actingAs($this->admin)
            ->withSession(['einrichtung_zugaenge' => [
                ['name' => 'Testperson Eins', 'rolle' => 'Admin', 'email' => 'eins@betrieb.example', 'passwort' => 'abc'],
            ]])
            ->get(route('admin.setup', 'finish'))->assertOk()->assertSee('eins@betrieb.example')->getContent();

        $this->assertSame(4, substr_count($html, '<th scope="col">'));
        $this->assertStringContainsString('<caption class="sr-only">Zugänge</caption>', $html);
    }

    #[Test]
    public function gesperrtes_entfernen_traegt_den_grund_als_text_im_knopf(): void
    {
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => $this->kategorie('FACH'), 'pflicht' => 1, 'aktiv' => 1,
        ]);
        $lernender = User::factory()->lernender()->create()->lernender;
        $lernender->update(['lehrberuf_id' => $lehrberuf->lehrberuf_id]);
        $belegung = ModulBelegung::factory()->create(['lernender_id' => $lernender->lernender_id, 'modul_id' => $modul->modul_id]);
        Note::factory()->create([
            'lernender_id' => $lernender->lernender_id, 'fach_id' => null, 'modul_belegung_id' => $belegung->modul_belegung_id,
            'kategorie_id' => $this->kategorie('FACH'),
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.master-data.professions.show', $lehrberuf->lehrberuf_id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~<button type="button" disabled[^>]*>\s*Entfernen\s*<span class="sr-only">Noten oder Prüfungen vorhanden</span>\s*</button>~u',
            $html,
        );
    }

    private function kategorie(string $code): int
    {
        return (int) Kategorie::where('code', $code)->value('kategorie_id');
    }
}
