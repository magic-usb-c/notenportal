<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Betreuung;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Audit Welle 3 und Rundgang: Leerzeile mit Kategorie, verlinkte Lernendenzahl, eigenes Modul zuerst, Trefferzahl der Suche, Feedback-Leerzustand. */
class AuditWelle3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        Konfiguration::vergessen();
    }

    #[Test]
    public function leere_notenliste_mit_kategoriefilter_nennt_die_kategorie(): void
    {
        DB::table('semester')->insert(['bezeichnung' => 'T-2', 'start_datum' => now()->subMonths(2)->toDateString(),
            'end_datum' => now()->addMonths(4)->toDateString(), 'sortierung' => 11]);
        Konfiguration::vergessen();
        $user = User::factory()->lernender()->create();
        $kategorie = DB::table('kategorien')->where('code', 'UEK')->first();

        $antwort = $this->actingAs($user)->get(route('learner.grades.index', ['kategorie_id' => $kategorie->kategorie_id]))->assertOk();

        $name = Konfiguration::ausDb()->kategorieName((int) $kategorie->kategorie_id);
        $antwort->assertSee('Keine Noten in '.$name.' im ', false);

        // Unbekannte Kategorie aus der URL zählt wie kein Filter
        $this->get(route('learner.grades.index', ['kategorie_id' => 999999]))->assertOk()
            ->assertDontSee('Keine Noten in –', false);
    }

    #[Test]
    public function lernendenzahl_im_admin_dashboard_fuehrt_zur_gefilterten_liste(): void
    {
        $admin = User::factory()->admin()->create();
        $mit = User::factory()->berufsbildner()->create(['vorname' => 'Mira', 'nachname' => 'Mitlernenden']);
        User::factory()->berufsbildner()->create(['vorname' => 'Otto', 'nachname' => 'Ohne']);
        Betreuung::factory()->create([
            'berufsbildner_id' => $mit->berufsbildner->berufsbildner_id,
            'lernender_id' => User::factory()->lernender()->create()->lernender->lernender_id,
        ]);

        $antwort = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $antwort->assertSee('href="'.e(route('admin.learners.index', ['berufsbildner_id' => $mit->berufsbildner->berufsbildner_id])).'"', false);
        $antwort->assertSee('aria-label="1 Lernende von Mira Mitlernenden"', false);
    }

    #[Test]
    public function modulseite_oeffnet_fuer_lernende_zuerst_ein_eigenes_modul(): void
    {
        $vorne = DB::table('module')->insertGetId(['modul_nummer' => '100', 'titel' => 'Fremdes Modul', 'aktiv' => 1]);
        $eigen = DB::table('module')->insertGetId(['modul_nummer' => '900', 'titel' => 'Eigenes Modul', 'aktiv' => 1]);
        $user = User::factory()->lernender()->create();
        $this->actingAs($user)->post(route('modules.enroll', $eigen))->assertSessionHas('success');

        $this->get(route('modules.index'))->assertOk()
            ->assertViewHas('modul', fn ($m) => $m->modul_id === $eigen);

        // Suche ohne eigenen Treffer: erster Treffer wie bisher
        $this->get(route('modules.index', ['suche' => 'Fremdes']))->assertOk()
            ->assertViewHas('modul', fn ($m) => $m->modul_id === $vorne);

        // Ohne Belegung (Admin) bleibt es beim ersten Modul der Liste
        $this->actingAs(User::factory()->admin()->create())->get(route('modules.index'))->assertOk()
            ->assertViewHas('modul', fn ($m) => $m->modul_id === $vorne);
    }

    #[Test]
    public function befehlspalette_meldet_die_trefferzahl_an_screenreader(): void
    {
        $this->actingAs(User::factory()->lernender()->create())->get(route('learner.dashboard'))->assertOk()
            ->assertSee('class="sr-only" role="status"', false)
            ->assertSee('1 Treffer', false);
    }

    #[Test]
    public function feedback_leerzustand_bietet_bei_ausgeschaltetem_knopf_das_einschalten_an(): void
    {
        $admin = User::factory()->admin()->create();
        $link = e(route('admin.operations.edit')).'#bedienung';

        $this->actingAs($admin)->get(route('admin.feedback.index'))->assertOk()
            ->assertSee('Noch keine Meldungen')->assertDontSee($link, false);

        Einstellungen::set(Einstellungen::FEEDBACK_KNOPF, '0');

        $this->get(route('admin.feedback.index'))->assertOk()
            ->assertSee('Der Feedback-Knopf ist ausgeschaltet.')
            ->assertSee('href="'.$link.'"', false);
    }
}
