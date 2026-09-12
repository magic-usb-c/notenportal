<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Note;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use App\Services\Calendar\CalendarExport;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruefungenControllerTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function pruefung(int $lernenderId, array $attribute = []): Pruefung
    {
        $fachId = Fach::factory()->create()->fach_id;

        return Pruefung::create(array_merge([
            'lernender_id' => $lernenderId,
            'fach_id' => $fachId,
            'titel' => 'LB1',
            'datum' => now()->addDays(5)->toDateString(),
            'gewichtung_prozent' => 20,
            'quelle' => Pruefung::MANUELL,
        ], $attribute));
    }

    #[Test]
    public function lernende_haben_keinen_zugriff_auf_die_verwaltungsseite(): void
    {
        $lernender = User::factory()->lernender()->create();

        $this->actingAs($lernender)->get('/admin/exams')->assertForbidden();
        $this->actingAs($lernender)->get('/trainer/exams')->assertForbidden();
    }

    #[Test]
    public function berufsbildner_sieht_nur_pruefungen_der_sichtbaren_lernenden(): void
    {
        $lernender = $this->neuerLernender(['vorname' => 'Lea', 'nachname' => 'Muster']);
        $verwalter = $this->verwalter('trainer', $lernender);
        $this->pruefung($lernender->lernender_id, ['titel' => 'Sichtbare Prüfung']);

        $fremder = $this->neuerLernender(['vorname' => 'Nico', 'nachname' => 'Fremd']);
        $this->pruefung($fremder->lernender_id, ['titel' => 'Fremde Prüfung']);

        $antwort = $this->actingAs($verwalter)->get(route('trainer.exams.index'));

        $antwort->assertOk();
        $antwort->assertSee('Sichtbare Prüfung');
        $antwort->assertDontSee('Fremde Prüfung');
    }

    #[Test]
    public function ohne_termine_erscheint_leerzustand_statt_leerer_wochengruppe(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.exams.index'))
            ->assertOk()
            ->assertSee('Keine Prüfungstermine für die aktuelle Auswahl.')
            ->assertDontSee('Diese Woche');
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function admin_sieht_pruefungen_aller_lernenden(string $bereich): void
    {
        $lernender1 = $this->neuerLernender();
        $lernender2 = $this->neuerLernender();
        $verwalter = User::factory()->{$bereich === 'admin' ? 'admin' : 'berufsbildner'}()->create();
        if ($bereich === 'trainer') {
            $this->betreue($verwalter, $lernender1);
            $this->betreue($verwalter, $lernender2);
        }
        $this->pruefung($lernender1->lernender_id, ['titel' => 'Erste Prüfung']);
        $this->pruefung($lernender2->lernender_id, ['titel' => 'Zweite Prüfung']);

        $antwort = $this->actingAs($verwalter)->get(route("{$bereich}.exams.index"));

        $antwort->assertOk()->assertSee('Erste Prüfung')->assertSee('Zweite Prüfung');
    }

    #[Test]
    public function berufsbildner_sieht_nur_aktiv_betreute_lernende(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $betreuter = $this->neuerLernender();
        $this->betreue($bb, $betreuter);
        $this->pruefung($betreuter->lernender_id, ['titel' => 'Betreute Prüfung']);

        $abgelaufen = $this->neuerLernender();
        $this->betreue($bb, $abgelaufen, 'abgelaufen');
        $this->pruefung($abgelaufen->lernender_id, ['titel' => 'Abgelaufene Betreuung Prüfung']);

        $antwort = $this->actingAs($bb)->get(route('trainer.exams.index'));

        $antwort->assertOk()->assertSee('Betreute Prüfung')->assertDontSee('Abgelaufene Betreuung Prüfung');
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function filter_nach_lernendem_schraenkt_liste_ein(string $bereich): void
    {
        $lernender1 = $this->neuerLernender(['vorname' => 'Anna', 'nachname' => 'Erst']);
        $lernender2 = $this->neuerLernender(['vorname' => 'Beat', 'nachname' => 'Zweit']);
        $verwalter = User::factory()->{$bereich === 'admin' ? 'admin' : 'berufsbildner'}()->create();
        if ($bereich === 'trainer') {
            $this->betreue($verwalter, $lernender1);
            $this->betreue($verwalter, $lernender2);
        }
        $this->pruefung($lernender1->lernender_id, ['titel' => 'Prüfung Anna']);
        $this->pruefung($lernender2->lernender_id, ['titel' => 'Prüfung Beat']);

        $antwort = $this->actingAs($verwalter)
            ->get(route("{$bereich}.exams.index", ['lernender_id' => $lernender1->lernender_id]));

        $antwort->assertOk()->assertSee('Prüfung Anna')->assertDontSee('Prüfung Beat');
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function filter_zeitraum_blendet_weiter_entfernte_pruefungen_aus(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $this->pruefung($lernender->lernender_id, ['titel' => 'Bald fällig', 'datum' => now()->addDays(3)->toDateString()]);
        $this->pruefung($lernender->lernender_id, ['titel' => 'Weit weg', 'datum' => now()->addDays(60)->toDateString()]);

        $antwort = $this->actingAs($verwalter)
            ->get(route("{$bereich}.exams.index", ['zeitraum' => '7']));

        $antwort->assertOk()->assertSee('Bald fällig')->assertDontSee('Weit weg');
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function kuerzlich_vergangene_pruefung_ohne_note_wird_angezeigt_mit_note_nicht(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);
        $this->pruefung($lernender->lernender_id, ['titel' => 'Unbenotet vergangen', 'datum' => now()->subDays(5)->toDateString()]);

        $semester = Semester::factory()->create([
            'start_datum' => now()->subMonths(3)->toDateString(),
            'end_datum' => now()->addMonths(3)->toDateString(),
        ]);
        $note = Note::create([
            'lernender_id' => $lernender->lernender_id,
            'kategorie_id' => DB::table('kategorien')->value('kategorie_id'),
            'semester_id' => $semester->semester_id,
            'fach_id' => Fach::factory()->create()->fach_id,
            'titel' => 'Note',
            'pruefungsdatum' => now()->subDays(6)->toDateString(),
            'note_wert' => 5.0,
            'gewichtung_prozent' => 100,
            'erfasst_von_benutzer_id' => $verwalter->benutzer_id,
        ]);
        $this->pruefung($lernender->lernender_id, [
            'titel' => 'Benotet vergangen', 'datum' => now()->subDays(5)->toDateString(), 'note_id' => $note->note_id,
        ]);

        $antwort = $this->actingAs($verwalter)->get(route("{$bereich}.exams.index"));

        $antwort->assertOk()->assertSee('Unbenotet vergangen')->assertDontSee('Benotet vergangen');
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function kalender_abo_link_und_token_reset(string $bereich): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($bereich, $lernender);

        $antwort = $this->actingAs($verwalter)->get(route('settings.calendar'));
        $antwort->assertOk();

        $altesToken = CalendarExport::token($verwalter);
        $antwort->assertSee($altesToken);

        $this->actingAs($verwalter)
            ->post(route('settings.calendar.token.reset'))
            ->assertRedirect(route('settings.calendar'))
            ->assertSessionHas('success');

        $neuesToken = CalendarExport::token($verwalter->fresh());
        $this->assertNotSame($altesToken, $neuesToken);

        $this->get(route('calendar.export', ['token' => $altesToken]))->assertNotFound();
        $this->get(route('calendar.export', ['token' => $neuesToken]))->assertOk();
    }
}
