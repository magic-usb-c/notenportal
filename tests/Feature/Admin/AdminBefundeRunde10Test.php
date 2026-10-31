<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Betreuung;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Lehrsemester;
use App\Support\Protokoll;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBefundeRunde10Test extends TestCase
{
    private function semesterAnlegen(User $admin, string $bez, string $von, string $bis, ?int $sortierung = null)
    {
        return $this->actingAs($admin)->post(route('admin.master-data.semesters.store'), array_filter([
            'bezeichnung' => $bez, 'start_datum' => $von, 'end_datum' => $bis, 'sortierung' => $sortierung,
        ], fn ($v) => $v !== null))->assertSessionHasNoErrors();
    }

    private function sortierung(string $bez): int
    {
        return (int) DB::table('semester')->where('bezeichnung', $bez)->value('sortierung');
    }

    #[Test]
    public function nachgetragenes_semester_ohne_sortierung_wird_chronologisch_einsortiert(): void
    {
        DB::table('semester')->delete();
        $admin = User::factory()->admin()->create();
        $this->semesterAnlegen($admin, 'A', '2025-08-01', '2026-01-31');
        $this->semesterAnlegen($admin, 'C', '2026-08-01', '2027-01-31');
        $this->semesterAnlegen($admin, 'B', '2026-02-01', '2026-07-31');
        $this->semesterAnlegen($admin, 'Z', '2025-02-01', '2025-07-31');

        $this->assertLessThan($this->sortierung('A'), $this->sortierung('Z'));
        $this->assertLessThan($this->sortierung('B'), $this->sortierung('A'));
        $this->assertLessThan($this->sortierung('C'), $this->sortierung('B'));

        $lernender = User::factory()->lernender(['lehrbeginn' => '2025-08-15'])->create()->lernender;
        Lehrsemester::vergessen();
        Konfiguration::vergessen();
        $id = fn (string $b) => (int) DB::table('semester')->where('bezeichnung', $b)->value('semester_id');
        $this->assertSame(2, Lehrsemester::nummer($lernender, $id('B')));
        $this->assertSame(3, Lehrsemester::nummer($lernender, $id('C')));
    }

    #[Test]
    public function einsortieren_bewahrt_luecken_und_bewusst_gesetzte_sortierung(): void
    {
        DB::table('semester')->delete();
        $admin = User::factory()->admin()->create();
        $this->semesterAnlegen($admin, 'A', '2025-08-01', '2026-01-31', 10);
        $this->semesterAnlegen($admin, 'C', '2026-08-01', '2027-01-31', 40);
        // Lücke zwischen 10 und 40: das neue Semester füllt sie, nichts anderes verschiebt sich
        $this->semesterAnlegen($admin, 'B', '2026-02-01', '2026-07-31');
        $this->assertSame([10, 20, 40], [$this->sortierung('A'), $this->sortierung('B'), $this->sortierung('C')]);

        // Bewusst gesetzte Sortierung bleibt, auch gegen die Chronologie
        $this->semesterAnlegen($admin, 'D', '2024-08-01', '2025-01-31', 500);
        $this->assertSame(500, $this->sortierung('D'));
    }

    #[Test]
    public function datumsaenderung_ohne_sortierung_ordnet_neu_ein(): void
    {
        DB::table('semester')->delete();
        $admin = User::factory()->admin()->create();
        $this->semesterAnlegen($admin, 'A', '2025-08-01', '2026-01-31');
        $this->semesterAnlegen($admin, 'B', '2026-02-01', '2026-07-31');
        $this->semesterAnlegen($admin, 'C', '2026-08-01', '2027-01-31');
        $a = DB::table('semester')->where('bezeichnung', 'A')->first();

        // A rückt hinter C (Sortierung leer = automatisch)
        $this->actingAs($admin)->put(route('admin.master-data.semesters.update', $a->semester_id), [
            'bezeichnung' => 'A', 'start_datum' => '2027-02-01', 'end_datum' => '2027-07-31', 'sortierung' => '',
        ])->assertSessionHasNoErrors();
        $this->assertGreaterThan($this->sortierung('C'), $this->sortierung('A'));
        $this->assertLessThan($this->sortierung('C'), $this->sortierung('B'));

        // Unveränderter Formularwert gilt nicht als bewusste Eingabe
        $b = DB::table('semester')->where('bezeichnung', 'B')->first();
        $this->actingAs($admin)->put(route('admin.master-data.semesters.update', $b->semester_id), [
            'bezeichnung' => 'B', 'start_datum' => '2024-02-01', 'end_datum' => '2024-07-31', 'sortierung' => $b->sortierung,
        ])->assertSessionHasNoErrors();
        $this->assertLessThan($this->sortierung('C'), $this->sortierung('B'));
        $this->assertLessThan($this->sortierung('A'), $this->sortierung('C'));
        $this->assertLessThan($this->sortierung('C'), $this->sortierung('B'));
    }

    #[Test]
    public function bewusst_geaenderte_sortierung_beim_bearbeiten_bleibt(): void
    {
        DB::table('semester')->delete();
        $admin = User::factory()->admin()->create();
        $this->semesterAnlegen($admin, 'A', '2025-08-01', '2026-01-31');
        $this->semesterAnlegen($admin, 'B', '2026-02-01', '2026-07-31');
        $a = DB::table('semester')->where('bezeichnung', 'A')->first();

        $this->actingAs($admin)->put(route('admin.master-data.semesters.update', $a->semester_id), [
            'bezeichnung' => 'A', 'start_datum' => '2025-08-01', 'end_datum' => '2026-01-31', 'sortierung' => 777,
        ])->assertSessionHasNoErrors();
        $this->assertSame(777, $this->sortierung('A'));
    }

    #[Test]
    public function loeschen_eines_semesters_laesst_luecke_bestehen(): void
    {
        DB::table('semester')->delete();
        $admin = User::factory()->admin()->create();
        $this->semesterAnlegen($admin, 'A', '2025-08-01', '2026-01-31');
        $this->semesterAnlegen($admin, 'B', '2026-02-01', '2026-07-31');
        $this->semesterAnlegen($admin, 'C', '2026-08-01', '2027-01-31');
        $vorher = $this->sortierung('C');

        $this->actingAs($admin)->delete(route('admin.master-data.semesters.destroy', DB::table('semester')->where('bezeichnung', 'B')->value('semester_id')))
            ->assertSessionHasNoErrors();
        $this->assertSame($vorher, $this->sortierung('C'));
    }

    #[Test]
    public function berufsbildner_mit_nur_deaktivierten_lernenden_laesst_sich_deaktivieren(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $weg = User::factory()->lernender()->create(['aktiv' => false]);
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $weg->lernender->lernender_id, 'gueltig_bis' => null]);

        $this->actingAs($admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0])
            ->assertSessionHas('success');
        $this->assertFalse((bool) $bb->fresh()->aktiv);
    }

    #[Test]
    public function berufsbildner_mit_nur_geloeschtem_lernenden_laesst_sich_umrollen(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $weg = User::factory()->lernender()->create();
        Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $weg->lernender->lernender_id, 'gueltig_bis' => null]);
        DB::table('lernende')->where('lernender_id', $weg->lernender->lernender_id)->update(['geloescht_am' => now()]);

        $this->actingAs($admin)->put(route('admin.users.update', $bb->benutzer_id), [
            'vorname' => $bb->vorname, 'nachname' => $bb->nachname, 'email' => $bb->email,
            'benutzername' => $bb->benutzername, 'rollen' => ['Admin'],
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function sperrmeldung_nennt_die_blockierenden_lernenden(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();
        $aktiv = User::factory()->lernender()->create(['vorname' => 'Berta', 'nachname' => 'Blockiert']);
        $weg = User::factory()->lernender()->create(['aktiv' => false, 'vorname' => 'Dora', 'nachname' => 'Deaktiviert']);
        foreach ([$aktiv, $weg] as $l) {
            Betreuung::factory()->create(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $l->lernender->lernender_id, 'gueltig_bis' => null]);
        }

        $r = $this->actingAs($admin)->post(route('admin.users.toggle-active', $bb->benutzer_id), ['aktiv' => 0]);
        $r->assertSessionHas('error');
        $meldung = (string) session('error');
        $this->assertStringContainsString('Berta Blockiert', $meldung);
        $this->assertStringNotContainsString('Dora', $meldung);
        $this->assertTrue((bool) $bb->fresh()->aktiv);

        $this->actingAs($admin)->put(route('admin.users.update', $bb->benutzer_id), [
            'vorname' => $bb->vorname, 'nachname' => $bb->nachname, 'email' => $bb->email,
            'benutzername' => $bb->benutzername, 'rollen' => ['Admin'],
        ])->assertSessionHasErrors('rollen');
        $this->assertStringContainsString('Berta Blockiert', session('errors')->first('rollen'));
    }

    #[Test]
    public function suche_nach_vollem_namen_findet_konto_in_liste_und_protokoll(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create(['vorname' => 'Annika', 'nachname' => 'Zumbrunnen', 'email' => 'qq9marker@demo.example']);
        $this->actingAs($bb);
        Protokoll::schreiben(Protokoll::ADMIN_BETRIEB_GEAENDERT);

        foreach (['Annika', 'Zumbrunnen', 'Annika Zumbrunnen', 'Zumbrunnen Annika', 'nika Zumb'] as $s) {
            $this->actingAs($admin);
            $this->assertStringContainsString('qq9marker@demo.example', $this->get(route('admin.users.index', ['suche' => $s]))->getContent(), "Benutzerliste: $s");
            $this->assertStringContainsString('Annika Zumbrunnen', $this->get(route('admin.activity.index', ['person' => $s]))->getContent(), "Protokoll: $s");
        }
        $this->assertStringNotContainsString('qq9marker@demo.example', $this->get(route('admin.users.index', ['suche' => 'Annika Meier']))->getContent());
        $this->assertStringNotContainsString('Annika Zumbrunnen', $this->get(route('admin.activity.index', ['person' => 'Annika Meier']))->getContent());
    }
}
