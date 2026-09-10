<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Lernender;
use App\Models\User;
use App\Support\Einrichtung;
use App\Support\Einstellungen;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EinrichtungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
    }

    #[Test]
    public function installation_fuehrt_vom_ersten_admin_bis_zu_lernenden(): void
    {
        $this->artisan('notenportal:erstes-admin-konto')->expectsOutputToContain('Startpasswort')->assertSuccessful();
        $this->artisan('notenportal:erstes-admin-konto')->expectsOutputToContain('vorhanden')->assertSuccessful();

        $admin = User::where('email', 'admin@notenportal.local')->firstOrFail();
        $this->assertTrue((bool) $admin->passwort_wechsel_noetig);
        $this->assertTrue(Einrichtung::offen());
        $admin->update(['passwort_wechsel_noetig' => false]);
        $this->actingAs($admin);

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.einrichtung'));
        foreach (array_keys(Einrichtung::SCHRITTE) as $schritt) {
            $this->get(route('admin.einrichtung', $schritt))->assertOk();
        }

        $this->post(route('admin.einrichtung.betrieb'), [
            'vorname' => 'Laura', 'nachname' => 'Frei', 'email' => 'laura@betrieb.ch', 'betrieb_name' => 'Muster AG',
            'note_gut' => '5', 'note_genuegend' => '4', 'note_kritisch' => '3.5', 'rundung_gesamt' => '0.1',
            'frist_inaktiv_tage' => 30, 'frist_lehrende_tage' => 60,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.einrichtung', 'kategorien'));
        $this->assertSame('Muster AG', Einstellungen::get(Einstellungen::BETRIEB_NAME));
        $this->assertSame('laura@betrieb.ch', $admin->fresh()->email);

        $kategorien = DB::table('kategorien')->get()->mapWithKeys(fn ($k) => [$k->kategorie_id => [
            'name' => $k->name, 'aktiv' => 1, 'rundung_element' => '0.5', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => 1,
            'promotion_min_schnitt' => $k->code === 'BMS' ? 4 : null, 'promotion_max_ungenuegend' => null, 'promotion_max_minuspunkte' => null,
        ]])->all();
        $this->post(route('admin.einrichtung.kategorien'), ['kategorien' => $kategorien])->assertSessionHasNoErrors();
        $this->assertTrue(Einrichtung::stand()['kategorien']['erledigt']);

        $semester = ['herbst' => '2024-08-01', 'fruehling' => '2025-02-01', 'bis_jahr' => 2026];
        $this->post(route('admin.einrichtung.semester'), $semester)->assertSessionHasNoErrors();
        $this->post(route('admin.einrichtung.semester'), $semester)->assertSessionHasNoErrors();
        $this->assertSame(['24/25-1', '24/25-2', '25/26-1', '25/26-2', '26/27-1', '26/27-2'],
            DB::table('semester')->orderBy('sortierung')->pluck('bezeichnung')->all());
        $this->assertSame('2025-01-31', DB::table('semester')->where('bezeichnung', '24/25-1')->value('end_datum'));

        $this->post(route('admin.einrichtung.lehrberufe'), [
            'berufe' => ['INAP'],
            'eigene' => [['kuerzel' => 'lab', 'name' => 'Laborant/in EFZ'], ['kuerzel' => '', 'name' => '']],
            'faecher' => ['BMS:D', 'ABU:SK'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['INAP', 'LAB'], DB::table('lehrberufe')->orderBy('kuerzel')->pluck('kuerzel')->all());
        $this->assertSame('BMS', DB::table('faecher')->where('name', 'Deutsch')->value('track_typ'));

        $lehrberuf = (int) DB::table('lehrberufe')->where('kuerzel', 'INAP')->value('lehrberuf_id');
        $this->post(route('admin.einrichtung.module'), ['lehrberuf_id' => $lehrberuf, 'schule' => 'Ohne Nummer', 'ziel' => 100])
            ->assertSessionHasErrors('schule');
        $this->post(route('admin.einrichtung.module'), [
            'lehrberuf_id' => $lehrberuf,
            'schule' => "431 Aufträge durchführen\nM162 – Daten modellieren",
            'uek' => 'ÜK-106: Datenbanken abfragen',
            'ziel' => 100,
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('lehrberuf_module')->where('lehrberuf_id', $lehrberuf)->count());
        $this->assertSame('UEK', DB::table('lehrberuf_module as lbm')->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->join('kategorien as k', 'k.kategorie_id', '=', 'lbm.kategorie_id')->where('m.modul_nummer', '106')->value('k.code'));

        $this->post(route('admin.einrichtung.personen'), ['personen' => [
            ['vorname' => 'Michael', 'nachname' => 'Baumann', 'email' => 'mb@betrieb.ch', 'rolle' => 'Berufsbildner'],
            ['vorname' => 'Anna', 'nachname' => 'Keller', 'email' => 'ak@betrieb.ch', 'rolle' => 'Admin'],
        ]])->assertSessionHasNoErrors()->assertSessionHas('einrichtung_zugaenge');
        $bb = (int) DB::table('berufsbildner')->value('berufsbildner_id');
        $this->assertTrue(User::where('email', 'ak@betrieb.ch')->firstOrFail()->hasRole('Admin'));

        $this->post(route('admin.einrichtung.lernende'), ['lernende' => [[
            'vorname' => 'Nina', 'nachname' => 'Huber', 'email' => 'nina@betrieb.ch', 'lehrberuf_id' => $lehrberuf,
            'lehrbeginn' => '2025-08-01', 'lehrende' => '2029-07-31', 'berufsbildner_id' => $bb, 'track' => 'BMS',
        ]]])->assertSessionHasNoErrors();

        $nina = Lernender::whereHas('benutzer', fn ($q) => $q->where('email', 'nina@betrieb.ch'))->firstOrFail();
        $this->assertSame(
            (int) DB::table('semester')->where('bezeichnung', '25/26-1')->value('semester_id'),
            (int) $nina->tracks()->value('start_semester_id'),
        );
        $this->assertTrue(DB::table('betreuungen')->where('lernender_id', $nina->lernender_id)->where('berufsbildner_id', $bb)->exists());
        $this->assertTrue((bool) $nina->benutzer->passwort_wechsel_noetig);

        $this->assertCount(3, session('einrichtung_zugaenge'));
        $this->get(route('admin.einrichtung', 'fertig'))->assertOk()->assertSee('nina@betrieb.ch');
        $this->post(route('admin.einrichtung.abschliessen'))->assertRedirect(route('admin.dashboard'))->assertSessionMissing('einrichtung_zugaenge');
        $this->assertFalse(Einrichtung::offen());
        $this->get(route('admin.dashboard'))->assertOk();
    }

    #[Test]
    public function betrieb_prueft_die_reihenfolge_der_notengrenzen(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $werte = ['betrieb_name' => 'Test AG', 'note_gut' => '5', 'note_genuegend' => '4', 'note_kritisch' => '3.5',
            'rundung_gesamt' => '0.1', 'frist_inaktiv_tage' => 30, 'frist_lehrende_tage' => 60];

        $this->put(route('admin.betrieb.update'), [...$werte, 'note_genuegend' => '5.5'])->assertSessionHasErrors('note_gut');
        $this->put(route('admin.betrieb.update'), [...$werte, 'note_kritisch' => '3.75'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('3.75', Einstellungen::get(Einstellungen::NOTE_KRITISCH));
        $this->get(route('admin.betrieb.edit'))->assertOk()->assertSee('Test AG');
    }

    #[Test]
    public function modulzeilen_werden_in_ueblichen_schreibweisen_erkannt(): void
    {
        $ergebnis = Einrichtung::moduleAusText("431 Aufträge durchführen\n\nÜK 106 - Datenbanken\nM319: Applikationen entwerfen\nkeine Nummer");

        $this->assertSame(['431', '106', '319'], array_column($ergebnis['module'], 'nummer'));
        $this->assertSame('Datenbanken', $ergebnis['module'][1]['titel']);
        $this->assertSame(['keine Nummer'], $ergebnis['fehler']);
    }
}
