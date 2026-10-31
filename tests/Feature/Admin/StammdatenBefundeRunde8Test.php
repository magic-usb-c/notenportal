<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Models\User;
use App\Services\Notifications\MailSettings;
use App\Support\Einrichtung;
use App\Support\Einstellungen;
use App\Support\Ungelesen;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StammdatenBefundeRunde8Test extends TestCase
{
    #[Test]
    public function lernortwechsel_eines_moduls_erhaelt_aktualisiert_am_der_noten(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $lb = Lehrberuf::factory()->create(['aktiv' => true]);
        $u = User::factory()->lernender()->create();
        $l = $u->lernender;
        $l->update(['lehrberuf_id' => $lb->lehrberuf_id]);
        $m = Modul::factory()->create();
        $uek = Kategorie::where('code', 'UEK')->value('kategorie_id');
        $fach = Kategorie::where('code', 'FACH')->value('kategorie_id');
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lb->lehrberuf_id, 'modul_id' => $m->modul_id, 'kategorie_id' => $uek, 'pflicht' => 1, 'aktiv' => 1]);
        $b = ModulBelegung::factory()->create(['lernender_id' => $l->lernender_id, 'modul_id' => $m->modul_id]);
        $n = Note::factory()->create(['lernender_id' => $l->lernender_id, 'fach_id' => null, 'modul_belegung_id' => $b->modul_belegung_id, 'kategorie_id' => $uek]);
        DB::table('noten')->where('note_id', $n->note_id)->update(['aktualisiert_am' => '2026-01-01 10:00:00', 'erfasst_von_benutzer_id' => $u->benutzer_id, 'aktualisiert_von_benutzer_id' => $u->benutzer_id]);
        DB::table('noten_gesehen')->insert(['note_id' => $n->note_id, 'viewer_benutzer_id' => $bb->benutzer_id, 'gesehen_am' => '2026-02-01 10:00:00']);
        $ungelesen = fn () => DB::table('noten as n')
            ->leftJoin('noten_gesehen as g', fn ($j) => $j->on('g.note_id', '=', 'n.note_id')->where('g.viewer_benutzer_id', $bb->benutzer_id))
            ->where('n.note_id', $n->note_id)->where(Ungelesen::bedingung($bb->benutzer_id))->count();
        $this->assertSame(0, $ungelesen());

        $this->actingAs($admin)->patch(route('admin.master-data.professions.modules.update', [$lb->lehrberuf_id, $m->modul_id]), ['kategorie_id' => $fach]);

        $row = DB::table('noten')->where('note_id', $n->note_id)->first();
        $this->assertEquals($fach, $row->kategorie_id);
        $this->assertSame('2026-01-01 10:00:00', $row->aktualisiert_am);
        $this->assertSame(0, $ungelesen());
    }

    #[Test]
    public function kategorie_bearbeiten_mit_leerer_sortierung_behaelt_den_bisherigen_wert(): void
    {
        $admin = User::factory()->admin()->create();
        $regeln = ['rundung_element' => '0.1', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => '1'];
        DB::table('kategorien')->insert(['code' => 'ZZ1', 'name' => 'Zeta', 'sortierung' => 40, 'aktiv' => true, 'rundung_element' => 0.1, 'rundung_schnitt' => 0.1, 'gewicht_gesamt' => 1]);
        $id = DB::table('kategorien')->where('code', 'ZZ1')->value('kategorie_id');

        $this->actingAs($admin)->put(route('admin.master-data.categories.update', $id), ['code' => 'ZZ1', 'name' => 'Zeta', 'sortierung' => '', 'aktiv' => '1'] + $regeln)
            ->assertRedirect(route('admin.master-data.categories.index'));

        $this->assertSame(40, (int) DB::table('kategorien')->where('kategorie_id', $id)->value('sortierung'));
    }

    #[Test]
    public function erledigter_mail_schritt_zeigt_im_abschluss_nicht_offen(): void
    {
        $this->seed(BasisSeeder::class);
        $admin = User::factory()->admin()->create(['passwort_wechsel_noetig' => false]);
        Einrichtung::oeffnen();
        Einstellungen::set(MailSettings::HOST, 'smtp.beispiel.test');
        $stand = Einrichtung::stand();
        $this->assertTrue($stand['mail']['erledigt']);
        $this->assertSame('smtp.beispiel.test', $stand['mail']['info']);

        $html = $this->actingAs($admin)->get(route('admin.setup', 'finish'))->assertOk()->getContent();
        $zeile = collect(explode('</li>', $html))
            ->map(fn ($l) => trim(preg_replace('/\s+/', ' ', strip_tags($l))))
            ->first(fn ($z) => str_contains($z, 'E-Mail') && str_contains($z, 'Bearbeiten'));
        $this->assertNotNull($zeile);
        $this->assertStringNotContainsString('offen', $zeile);
    }

    #[Test]
    public function offener_schritt_ohne_info_zeigt_im_abschluss_weiter_offen(): void
    {
        $this->seed(BasisSeeder::class);
        $admin = User::factory()->admin()->create(['passwort_wechsel_noetig' => false]);
        Einrichtung::oeffnen();

        $html = $this->actingAs($admin)->get(route('admin.setup', 'finish'))->assertOk()->getContent();
        $this->assertStringContainsString('offen', strip_tags($html));
    }
}
