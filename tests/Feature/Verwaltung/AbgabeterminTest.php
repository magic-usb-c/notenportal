<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Lernender;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Modulstatus (Rückmeldung #14): Admin und Berufsbildner können Abgabetermine (Pruefung mit
 * art=abgabe) je Modul eines sichtbaren Lernenden erfassen/bearbeiten/löschen – reguläre Prüfungen
 * bleiben unverändert Sache des Lernenden.
 */
class AbgabeterminTest extends TestCase
{
    use VerwaltungTestHilfen;

    private int $lehrberuf;

    private int $modul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'AGT', 'name' => 'Abgabetermin-Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '432', 'titel' => 'Projekt durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        Konfiguration::vergessen();
    }

    private function lernender(): Lernender
    {
        return $this->neuerLernender(['vorname' => 'Lea', 'nachname' => 'Muster'], ['lehrberuf_id' => $this->lehrberuf]);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function erfasst_einen_abgabetermin_fuer_ein_modul(string $rolle): void
    {
        $lernender = $this->lernender();
        $user = $this->verwalter($rolle, $lernender);

        $antwort = $this->actingAs($user)->post(route("{$rolle}.exams.store"), [
            'lernender_id' => $lernender->lernender_id,
            'bezug' => 'modul:'.$this->modul,
            'titel' => 'Projektbericht',
            'datum' => now()->addWeeks(2)->toDateString(),
            'gewichtung_prozent' => 30,
        ]);

        $antwort->assertRedirect();
        $antwort->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pruefungen', [
            'lernender_id' => $lernender->lernender_id,
            'modul_id' => $this->modul,
            'titel' => 'Projektbericht',
            'art' => Pruefung::ART_ABGABE,
        ]);
    }

    #[Test]
    public function lernende_koennen_keine_abgabetermine_erfassen(): void
    {
        $lernender = $this->lernender();
        $user = User::find($lernender->benutzer_id);

        $this->actingAs($user)->post(route('admin.exams.store'), [
            'lernender_id' => $lernender->lernender_id,
            'bezug' => 'modul:'.$this->modul,
            'titel' => 'Projektbericht',
            'datum' => now()->addWeeks(2)->toDateString(),
        ])->assertForbidden();
    }

    #[Test]
    public function berufsbildner_kann_fuer_nicht_betreute_lernende_keinen_termin_erfassen(): void
    {
        $lernender = $this->lernender();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($bb)->post(route('trainer.exams.store'), [
            'lernender_id' => $lernender->lernender_id,
            'bezug' => 'modul:'.$this->modul,
            'titel' => 'Projektbericht',
            'datum' => now()->addWeeks(2)->toDateString(),
        ])->assertNotFound();
    }

    #[Test]
    public function bearbeitet_und_loescht_einen_abgabetermin(): void
    {
        $lernender = $this->lernender();
        $admin = $this->verwalter('admin', $lernender);
        $termin = Pruefung::create([
            'lernender_id' => $lernender->lernender_id,
            'modul_id' => $this->modul,
            'art' => Pruefung::ART_ABGABE,
            'titel' => 'Entwurf',
            'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => 20,
            'quelle' => Pruefung::MANUELL,
        ]);

        $this->actingAs($admin)->put(route('admin.exams.update', $termin->pruefung_id), [
            'lernender_id' => $lernender->lernender_id,
            'bezug' => 'modul:'.$this->modul,
            'titel' => 'Schlussfassung',
            'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => 25,
        ])->assertRedirect();
        $this->assertDatabaseHas('pruefungen', ['pruefung_id' => $termin->pruefung_id, 'titel' => 'Schlussfassung']);

        $this->actingAs($admin)->delete(route('admin.exams.destroy', $termin->pruefung_id), [
            'lernender_id' => $lernender->lernender_id,
        ])->assertRedirect();
        $this->assertDatabaseMissing('pruefungen', ['pruefung_id' => $termin->pruefung_id]);
    }

    #[Test]
    public function normale_pruefung_kann_nicht_ueber_die_abgabe_route_bearbeitet_werden(): void
    {
        $lernender = $this->lernender();
        $admin = $this->verwalter('admin', $lernender);
        $pruefung = Pruefung::create([
            'lernender_id' => $lernender->lernender_id,
            'modul_id' => $this->modul,
            'titel' => 'Reguläre Prüfung',
            'datum' => now()->addWeek()->toDateString(),
            'gewichtung_prozent' => 50,
            'quelle' => Pruefung::MANUELL,
        ]);

        $this->actingAs($admin)->put(route('admin.exams.update', $pruefung->pruefung_id), [
            'lernender_id' => $lernender->lernender_id,
            'bezug' => 'modul:'.$this->modul,
            'titel' => 'Manipuliert',
            'datum' => now()->addWeek()->toDateString(),
        ])->assertNotFound();
    }

    #[Test]
    public function admin_sieht_das_formular_erst_wenn_ein_lernender_gefiltert_ist(): void
    {
        $lernender = $this->lernender();
        $admin = $this->verwalter('admin', $lernender);

        $this->actingAs($admin)->get(route('admin.exams.index'))
            ->assertOk()
            ->assertDontSee('id="abgabe-bezug"', false);

        $this->actingAs($admin)->get(route('admin.exams.index', ['lernender_id' => $lernender->lernender_id]))
            ->assertOk()
            ->assertSee(__('Abgabetermin'));
    }
}
