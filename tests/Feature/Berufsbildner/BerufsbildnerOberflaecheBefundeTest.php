<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\Semester;
use App\Models\User;
use App\Support\Einrichtung;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Funktionsprüfung R6, Gruppe «berufsbildner»: Filterleiste in Kartenbreite, eine Hauptaktion rechts,
 * «morgen» statt «in 1 Tagen», neutrales Symbol der Kachel «Alle», Stufen-Chips mit eigenem Hover-Ton.
 */
class BerufsbildnerOberflaecheBefundeTest extends TestCase
{
    private int $kategorieId;

    private Lehrberuf $lehrberuf;

    /** @var list<Semester> */
    private array $semester = [];

    protected function setUp(): void
    {
        parent::setUp();
        Einrichtung::abschliessen();

        $this->kategorieId = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $this->lehrberuf = Lehrberuf::factory()->create();

        $start = Carbon::parse('2020-08-01');
        foreach ([1, 2] as $i) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $this->semester[] = Semester::factory()->create([
                'bezeichnung' => 'S'.$i.'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $i,
            ]);
            $start = $ende->copy()->addDay();
        }
    }

    #[Test]
    public function filterleiste_der_lernendenliste_endet_an_der_kartenkante_des_berufsbildners(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.5, $bb);

        $form = $this->filterFormular($this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk()->getContent());
        $this->assertStringContainsString('max-w-7xl', $form, 'Filterleiste und Zähler folgen der Kartenbreite');

        $this->lernender('Brunner', 4.0, 4.5);
        $admin = $this->filterFormular($this->actingAs(User::factory()->admin()->create())->get(route('admin.learners.index'))->assertOk()->getContent());
        $this->assertStringNotContainsString('max-w-7xl', $admin, 'Der Admin füllt das Fenster');
    }

    #[Test]
    public function die_hauptaktion_steht_je_seite_genau_einmal_und_ganz_rechts(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $user = $this->lernender('Ammann', 4.0, 4.5, $bb);
        $id = $user->lernender->lernender_id;

        $show = $this->actingAs($bb)->get(route('trainer.learners.show', $id))->assertOk()->getContent();
        $this->assertSame(1, substr_count($show, 'data-hauptaktion'));
        $this->assertGreaterThan(strpos($show, route('trainer.learners.grades.print', $id)), strpos($show, 'data-hauptaktion'), 'hinter «Drucken»');
        $this->assertGreaterThan(strpos($show, 'aria-label="Weitere Aktionen"'), strpos($show, 'data-hauptaktion'), 'hinter dem Menü');
        $this->assertMatchesRegularExpression('/data-hauptaktion[^>]*>\s*<a [^>]*learners\/'.$id.'\/grades"/s', $show);

        foreach (['trainer.dashboard' => 'trainer.learners.create', 'trainer.learners.index' => 'trainer.learners.create'] as $route => $ziel) {
            $html = $this->actingAs($bb)->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'data-hauptaktion'), $route);
            $this->assertMatchesRegularExpression('/data-hauptaktion[^>]*>\s*<a href="'.preg_quote(route($ziel), '/').'"/s', $html, $route);
        }
    }

    #[Test]
    public function lehrende_morgen_steht_als_morgen_nicht_als_in_1_tagen(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $morgen = $this->lernender('Ammann', 4.0, 4.5, $bb);
        $drei = $this->lernender('Brunner', 4.0, 4.5, $bb);
        Lernender::whereKey($morgen->lernender->lernender_id)->update(['lehrende' => now()->addDay()->toDateString()]);
        Lernender::whereKey($drei->lernender->lernender_id)->update(['lehrende' => now()->addDays(3)->toDateString()]);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk();
        $html->assertSee('Lehre endet bald');
        $html->assertDontSeeText('in 1 Tagen');
        $html->assertDontSeeText('in 0 Tagen');
        $html->assertSeeText('morgen');
        $html->assertSeeText('in 3 Tagen');
    }

    #[Test]
    public function das_symbol_der_kachel_alle_ist_neutral_der_akzent_bleibt_der_gewaehlten_kachel(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.0, 4.5, $bb);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('bg-accent/12 text-accent-text', $html);
        $this->assertStringContainsString('bg-accent/12! border-accent/50', $html, 'gewählte Kachel behält den Akzent');
    }

    #[Test]
    public function stufen_chips_behalten_beim_hover_ihren_ton(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->lernender('Ammann', 4.5, 3.0, $bb);
        $this->lernender('Brunner', 4.5, 3.7, $bb);

        $html = $this->actingAs($bb)->get(route('trainer.dashboard'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<a [^>]*class="([^"]*bg-note-ungenuegend\/14[^"]*)"/', $html, $u));
        $this->assertSame(1, preg_match('/<a [^>]*class="([^"]*bg-note-knapp\/14[^"]*)"/', $html, $k));
        $this->assertStringContainsString('hover:bg-note-ungenuegend/', $u[1]);
        $this->assertStringContainsString('hover:bg-note-knapp/', $k[1]);
        $this->assertStringNotContainsString('hover:bg-fill-2', $u[1]);
        $this->assertStringNotContainsString('hover:bg-fill-2', $k[1]);
    }

    /** Das GET-Formular der Filterleiste (Suchfeld «suche»). */
    private function filterFormular(string $html): string
    {
        $this->assertSame(1, preg_match('/<form[^>]*>(?:(?!<\/form>).)*data-np-filterstatus/s', $html, $m));
        preg_match('/<form[^>]*>/', $m[0], $kopf);

        return $kopf[0];
    }

    private function lernender(string $nachname, float $vorher, float $jetzt, ?User $bb = null): User
    {
        $user = User::factory()->lernender([
            'lehrberuf_id' => $this->lehrberuf->lehrberuf_id,
            'lehrbeginn' => now()->subMonths(18)->toDateString(),
            'lehrende' => now()->addMonths(30)->toDateString(),
        ])->create(['nachname' => $nachname]);

        if ($bb !== null) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id]);
        }

        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $this->kategorieId]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        foreach ([[$this->semester[0], $vorher], [$this->semester[1], $jetzt]] as [$semester, $wert]) {
            DB::table('noten')->insert([
                'lernender_id' => $user->lernender->lernender_id,
                'kategorie_id' => $this->kategorieId,
                'semester_id' => $semester->semester_id,
                'fach_id' => $fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note',
                'pruefungsdatum' => Carbon::parse($semester->start_datum)->addDays(10)->toDateString(),
                'note_wert' => $wert,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $user->benutzer_id,
            ]);
        }

        return $user;
    }
}
