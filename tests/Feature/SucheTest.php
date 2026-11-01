<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

class SucheTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function berufsbildner_findet_nur_betreute_lernende(): void
    {
        $eigener = $this->neuerLernender(['vorname' => 'Zora', 'nachname' => 'Eigen']);
        $this->neuerLernender(['vorname' => 'Zora', 'nachname' => 'Fremd']);
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $eigener);

        $treffer = $this->actingAs($bb)->getJson(route('search', ['q' => 'Zora']))->assertOk()->json();

        $this->assertSame(['Zora Eigen'], array_column($treffer, 'label'));
        $this->assertStringContainsString('/trainer/learners/'.$eigener->lernender_id, $treffer[0]['url']);
    }

    #[Test]
    public function admin_findet_lernende_und_konten_lernende_nichts(): void
    {
        $this->neuerLernender(['vorname' => 'Zora', 'nachname' => 'Muster']);
        User::factory()->berufsbildner()->create(['vorname' => 'Zora', 'nachname' => 'Bildnerin']);
        $admin = User::factory()->admin()->create();

        $gruppen = array_column($this->actingAs($admin)->getJson(route('search', ['q' => 'zora']))->json(), 'gruppe');
        $this->assertContains('Lernende', $gruppen);
        $this->assertContains('Konten', $gruppen);

        $lernender = User::factory()->lernender()->create();
        $this->actingAs($lernender)->getJson(route('search', ['q' => 'zora']))->assertOk()->assertExactJson([]);

        // Direkter Aufruf im Browser zeigt kein rohes JSON
        $this->actingAs($lernender)->get(route('search'))->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function lernender_findet_eigene_note_aber_nicht_fremde(): void
    {
        $eigener = $this->neuerLernender();
        $fremder = $this->neuerLernender();
        Note::factory()->create(['lernender_id' => $eigener->lernender_id, 'titel' => 'Zwischenpruefung Mathematik']);
        Note::factory()->create(['lernender_id' => $fremder->lernender_id, 'titel' => 'Zwischenpruefung Mathematik']);

        $treffer = $this->actingAs($eigener->benutzer)
            ->getJson(route('search', ['q' => 'Zwischenpruefung']))->assertOk()->json();

        $this->assertCount(1, $treffer);
        $this->assertSame('Eigene Noten', $treffer[0]['gruppe']);
        $this->assertStringContainsString(route('learner.grades.index'), $treffer[0]['url']);
    }

    #[Test]
    public function stufennote_zeigt_in_der_suche_die_stufe(): void
    {
        $lernender = $this->neuerLernender();
        $semester = Semester::factory()->create([
            'start_datum' => now()->subMonths(3)->toDateString(),
            'end_datum' => now()->addMonths(3)->toDateString(),
        ]);
        $this->bmsTrack($lernender, (int) $semester->semester_id);
        $sport = Fach::factory()->create(['skala' => 'stufe', 'zaehlt' => false, 'name' => 'Sport']);
        foreach (['Ausdauerlauf' => 'B', 'Geraeteturnen' => 'd'] as $titel => $stufe) {
            Note::create([
                'lernender_id' => $lernender->lernender_id,
                'kategorie_id' => Kategorie::where('code', 'BMS')->value('kategorie_id'),
                'semester_id' => $semester->semester_id,
                'erfasst_von_benutzer_id' => $lernender->benutzer_id,
                'gewichtung_prozent' => 100,
                'fach_id' => $sport->fach_id,
                'titel' => $titel,
                'pruefungsdatum' => now()->subDays(2)->toDateString(),
                'note_wert' => null,
                'note_stufe' => $stufe,
            ]);
        }

        $sub = fn (string $q) => collect($this->actingAs($lernender->benutzer)
            ->getJson(route('search', ['q' => $q]))->assertOk()->json())->firstWhere('gruppe', 'Eigene Noten')['sub'];

        $this->assertStringStartsWith('Note B ·', $sub('Ausdauerlauf'));
        $this->assertStringStartsWith('Note dispensiert ·', $sub('Geraeteturnen'));
    }

    #[Test]
    public function lernender_findet_eigenes_fach_zum_erfassen(): void
    {
        $lernender = $this->neuerLernender();
        $fach = Fach::factory()->create(['name' => 'Mathematik Vertiefung', 'track_typ' => null]);
        DB::table('lehrberuf_faecher')->insert([
            'lehrberuf_id' => $lernender->lehrberuf_id,
            'fach_id' => $fach->fach_id,
            'aktiv' => 1,
        ]);

        $treffer = $this->actingAs($lernender->benutzer)
            ->getJson(route('search', ['q' => 'Vertiefung']))->assertOk()->json();

        $this->assertCount(1, $treffer);
        $this->assertSame('Fächer & Module', $treffer[0]['gruppe']);
        $this->assertStringContainsString('bezug=fach', urldecode($treffer[0]['url']));
    }

    #[Test]
    public function navigation_zeigt_rollengerechte_eintraege(): void
    {
        $this->actingAs(User::factory()->lernender()->create())->get(route('learner.dashboard'))
            ->assertSee('>Agenda<', false)->assertSee('Rechner')->assertDontSee('Stammdaten');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertSee('Stammdaten')->assertSee('Benutzerkonten');
    }
}
