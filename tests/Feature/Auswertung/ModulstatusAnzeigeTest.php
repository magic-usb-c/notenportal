<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\Fach;
use App\Models\Lernender;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Modulstatus;
use App\Support\Zahl;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Modulstatus (Rückmeldung #14) auf der Notenübersicht des Lernenden und im Verwaltungs-Cockpit:
 * Dauer seit Beginn, nächster Termin und bewerteter Anteil je Modul.
 */
class ModulstatusAnzeigeTest extends TestCase
{
    use VerwaltungTestHilfen;

    private int $lehrberuf;

    private int $modul;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'MST', 'name' => 'Modulstatus-Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '433', 'titel' => 'Kundendaten verwalten']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->modul,
            'kategorie_id' => DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')]);
        $this->semester = DB::table('semester')->insertGetId([
            'bezeichnung' => 'MST-1', 'start_datum' => now()->subMonths(6)->toDateString(), 'end_datum' => now()->addMonths(6)->toDateString(), 'sortierung' => 20,
        ]);
        Konfiguration::vergessen();
    }

    /**
     * Note + geplanter Abgabetermin fürs Modul, damit der Modulstatus (Beginn, Termin, Anteil) berechnet
     * werden kann. Postet als der/die Lernende selbst (unabhängig davon, wer im Test aktuell angemeldet ist).
     */
    private function bereite(int $lernenderId): void
    {
        $benutzer = User::find(Lernender::findOrFail($lernenderId)->benutzer_id);
        $this->actingAs($benutzer)->post(route('learner.grades.store'), [
            'typ' => 'modul', 'modul_id' => $this->modul,
            'pruefungsdatum' => now()->subDays(30)->toDateString(),
            'note_wert' => '5.0', 'gewichtung_prozent' => 40,
        ])->assertSessionHasNoErrors();

        Pruefung::create([
            'lernender_id' => $lernenderId,
            'modul_id' => $this->modul,
            'art' => Pruefung::ART_ABGABE,
            'titel' => 'Schlussdokumentation',
            'datum' => now()->addDays(10)->toDateString(),
            'gewichtung_prozent' => 20,
            'quelle' => Pruefung::MANUELL,
        ]);
    }

    #[Test]
    public function notenuebersicht_zeigt_dauer_naechsten_termin_und_bewerteten_anteil(): void
    {
        $user = User::factory()->lernender(['lehrberuf_id' => $this->lehrberuf])->create();
        $this->actingAs($user);
        $this->bereite((int) $user->lernender->lernender_id);

        $this->get(route('learner.grades.index', ['semester_id' => $this->semester]))
            ->assertOk()
            ->assertSee('seit 4 Wochen')
            ->assertSee('Schlussdokumentation')
            ->assertSee(Zahl::prozent(40), false);
    }

    #[Test]
    public function cockpit_zeigt_modulstatus_karte(): void
    {
        $lernender = $this->neuerLernender(['vorname' => 'Nora', 'nachname' => 'Test'], ['lehrberuf_id' => $this->lehrberuf]);
        $this->bereite((int) $lernender->lernender_id);
        $admin = $this->verwalter('admin', $lernender);

        $this->actingAs($admin)->get(route('admin.learners.show', $lernender->lernender_id))
            ->assertOk()
            ->assertSee(__('Modulstatus'))
            ->assertSee('Kundendaten verwalten')
            ->assertSee(Zahl::prozent(40), false);
    }

    #[Test]
    public function fach_zeile_eines_frueheren_semesters_zeigt_keine_termine_des_laufenden_semesters(): void
    {
        $lernender = $this->neuerLernender(['vorname' => 'Sina', 'nachname' => 'Test'], ['lehrberuf_id' => $this->lehrberuf]);
        $id = (int) $lernender->lernender_id;
        $alt = Semester::factory()->create(['start_datum' => now()->subMonths(18)->toDateString(), 'end_datum' => now()->subMonths(7)->subDay()->toDateString()]);
        $fach = Fach::factory()->create();
        Note::factory()->create(['lernender_id' => $id, 'fach_id' => $fach->fach_id, 'semester_id' => $alt->semester_id, 'pruefungsdatum' => now()->subMonths(10)->toDateString(), 'note_wert' => 4.0]);
        Note::factory()->create(['lernender_id' => $id, 'fach_id' => $fach->fach_id, 'semester_id' => $this->semester, 'pruefungsdatum' => now()->subMonths(2)->toDateString(), 'note_wert' => 5.0]);
        Pruefung::create(['lernender_id' => $id, 'fach_id' => $fach->fach_id, 'titel' => 'Zukunftspruefung', 'datum' => now()->addDays(10)->toDateString(), 'gewichtung_prozent' => 100, 'quelle' => Pruefung::MANUELL]);
        Konfiguration::vergessen();

        $zeilen = collect((new Modulstatus)->fuerLernenden($id))->where('fach_id', $fach->fach_id);
        $zeileAlt = $zeilen->firstWhere('semester_id', $alt->semester_id);
        $zeileNeu = $zeilen->firstWhere('semester_id', $this->semester);

        $this->assertNotNull($zeileAlt);
        $this->assertNotNull($zeileNeu);
        $this->assertNull($zeileAlt['naechster_termin']);
        $this->assertNull($zeileAlt['letzter_termin']);
        $this->assertNull($zeileAlt['fortschritt_prozent']);
        $this->assertSame('Zukunftspruefung', $zeileNeu['naechster_termin']['titel']);
    }
}
