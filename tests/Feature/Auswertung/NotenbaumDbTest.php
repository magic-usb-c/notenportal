<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\Note;
use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\BaumErgebnis;
use App\Services\Auswertung\NotenQuelle;
use Database\Seeders\BasisSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notenbaum über die Datenbank: Noten über die echten Formulare erfasst, Baum und Positionen in den
 * Tabellen, Auswertung über NotenQuelle – derselbe Weg wie im Portal (docs/auftrag/NOTENBAUM.md §3).
 */
class NotenbaumDbTest extends TestCase
{
    private int $lehrberuf;

    private int $fach;

    private int $uek;

    private int $abu;

    private int $englisch;

    private int $abuFach;

    private int $schulmodul;

    private int $uekModul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $kat = fn (string $code) => (int) DB::table('kategorien')->where('code', $code)->value('kategorie_id');
        $this->fach = $kat('FACH');
        $this->uek = $kat('UEK');
        $this->abu = $kat('ABU');

        $this->lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'NBT', 'name' => 'Notenbaum-Test EFZ']);
        DB::table('semester')->insert([
            ['bezeichnung' => 'NB-1', 'start_datum' => '2024-08-01', 'end_datum' => '2025-01-31', 'sortierung' => 1],
            ['bezeichnung' => 'NB-2', 'start_datum' => '2025-02-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 2],
        ]);

        $this->englisch = $this->fachAnlegen('Englisch', 'EN', $this->fach);
        $this->abuFach = $this->fachAnlegen('Allgemeinbildung', 'ABU', $this->abu);

        $this->schulmodul = DB::table('module')->insertGetId(['modul_nummer' => '901', 'titel' => 'Testmodul Schule']);
        $this->uekModul = DB::table('module')->insertGetId(['modul_nummer' => '991', 'titel' => 'Testmodul ÜK']);
        DB::table('lehrberuf_module')->insert([
            ['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->schulmodul, 'kategorie_id' => $this->fach],
            ['lehrberuf_id' => $this->lehrberuf, 'modul_id' => $this->uekModul, 'kategorie_id' => $this->uek],
        ]);
        Konfiguration::vergessen();
    }

    #[Test]
    public function frische_installation_hat_die_promotionsregel_der_berufsmaturitaet(): void
    {
        $bms = DB::table('kategorien')->where('code', 'BMS')->first();

        $this->assertEqualsWithDelta(4.0, (float) $bms->promotion_min_schnitt, 1e-9);
        $this->assertSame(2, (int) $bms->promotion_max_ungenuegend);
        $this->assertEqualsWithDelta(2.0, (float) $bms->promotion_max_minuspunkte, 1e-9);
    }

    #[Test]
    public function fall_a_ueber_die_datenbank(): void
    {
        [$user, $id] = $this->lernender();
        $this->baumAnlegen();
        $this->noten($user, schulmodul: 5.0, uek: 4.0, englisch: 4.5, abu: 4.5);
        $this->positionen($user, $id, ['ipa' => 5.0, 'ab_schlussarbeit' => 5.0, 'ab_schlusspruefung' => 4.0]);

        $a = app(NotenQuelle::class)->auswertung($id);
        $baum = array_values($a->baeume)[0];

        $this->assertEqualsWithDelta(4.8, $baum->knoten('ik')->note, 1e-9);
        $this->assertEqualsWithDelta(4.5, $baum->knoten('ab')->note, 1e-9);
        $this->assertEqualsWithDelta(4.8, $a->gesamtNote, 1e-9);
        $this->assertSame(BaumErgebnis::BESTANDEN, $baum->status);
    }

    #[Test]
    public function fall_d_ueber_die_datenbank_und_mehrere_lernende_gebuendelt(): void
    {
        [$user, $id] = $this->lernender();
        [$ohneBaumUser, $ohneBaum] = $this->lernender($andererBeruf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'OBT', 'name' => 'Ohne Baum EFZ']));
        DB::table('lehrberuf_module')->insert([
            ['lehrberuf_id' => $andererBeruf, 'modul_id' => $this->schulmodul, 'kategorie_id' => $this->fach],
            ['lehrberuf_id' => $andererBeruf, 'modul_id' => $this->uekModul, 'kategorie_id' => $this->uek],
        ]);
        $this->baumAnlegen();
        $this->noten($user, schulmodul: 6.0, uek: 1.0);
        $this->noten($ohneBaumUser, schulmodul: 6.0, uek: 1.0);

        $alle = app(NotenQuelle::class)->auswertungen([$id, $ohneBaum]);

        $this->assertEqualsWithDelta(5.0, array_values($alle[$id]->baeume)[0]->knoten('ik')->note, 1e-9);
        // Lehrberuf ohne Baum: flache Kategorie-Gewichtung wie bisher (NOTENBAUM.md §3.4)
        $this->assertSame([], $alle[$ohneBaum]->baeume);
        $this->assertEqualsWithDelta(3.5, $alle[$ohneBaum]->gesamtNote, 1e-9);
    }

    #[Test]
    public function inaktiver_baum_rechnet_nicht(): void
    {
        [$user, $id] = $this->lernender();
        $baum = $this->baumAnlegen();
        DB::table('notenbaeume')->where('baum_id', $baum)->update(['aktiv' => false]);
        $this->noten($user, schulmodul: 6.0, uek: 1.0);

        $this->assertSame([], app(NotenQuelle::class)->auswertung($id)->baeume);
    }

    #[Test]
    public function sport_als_stufe_erfassen_anzeigen_und_nie_rechnen(): void
    {
        [$user, $id] = $this->lernender();
        $sport = $this->fachAnlegen('Sport', 'SP', $this->fach, skala: 'stufe', zaehlt: false);
        $this->actingAs($user);

        $this->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $sport, 'pruefungsdatum' => now()->toDateString(), 'note_stufe' => 'C',
        ])->assertSessionHasNoErrors();
        $this->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $this->englisch, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => '5.0',
        ])->assertSessionHasNoErrors();

        $note = Note::where('fach_id', $sport)->firstOrFail();
        $this->assertNull($note->note_wert);
        $this->assertSame('C', $note->note_stufe);

        $a = app(NotenQuelle::class)->auswertung($id);
        $this->assertEqualsWithDelta(5.0, $a->gesamtNote, 1e-9);
        $this->assertEqualsWithDelta(5.0, $a->kategorien[$this->fach]['note'], 1e-9);
        $this->assertCount(1, $a->elemente, 'Stufen erzeugen kein rechnendes Element');

        $this->get(route('learner.grades.index'))->assertOk()->assertSee('Sport');

        // Im Zeugnis sichtbar: Notenblatt und CSV zeigen die Stufe, nie «0.00»
        $this->get(route('learner.grades.print'))->assertOk()->assertSeeInOrder(['Sport', '<b>C</b>'], false);
        $csv = $this->get(route('learner.grades.export'))->assertOk()->streamedContent();
        $this->assertMatchesRegularExpression('/;Sport;;C;100/', $csv);
        $this->assertDoesNotMatchRegularExpression('/;Sport;;0\.00;/', $csv);

        $admin = User::factory()->admin()->create();
        $csv = $this->actingAs($admin)->get(route('admin.learners.grades.export', $id))->assertOk()->streamedContent();
        $this->assertMatchesRegularExpression('/;Sport;;C;100/', $csv);
        $this->actingAs($admin)->get(route('admin.learners.grades.print', $id))->assertOk()->assertSee('Sport');
    }

    #[Test]
    public function rollback_des_notenbaums_bricht_bei_stufen_oder_abschlussnoten_vor_jeder_ddl_ab(): void
    {
        $migration = require database_path('migrations/2026_10_01_000001_notenbaum.php');
        [$user, $id] = $this->lernender();
        $baum = $this->baumAnlegen();

        // Abschlussnote (IPA o. ä.) erfasst: Rollback würde sie mit der Tabelle löschen
        $knoten = (int) DB::table('notenbaum_knoten')->where('baum_id', $baum)->where('typ', 'manuell')->value('knoten_id');
        DB::table('notenbaum_positionen')->insert(['lernender_id' => $id, 'knoten_id' => $knoten, 'note_wert' => 5.0, 'erfasst_von_benutzer_id' => $user->benutzer_id, 'erstellt_am' => now(), 'aktualisiert_am' => now()]);
        $this->assertThrows(fn () => $migration->down(), \RuntimeException::class, 'Abschlussnote');
        $this->assertTrue(Schema::hasTable('notenbaum_positionen'));
        DB::table('notenbaum_positionen')->delete();

        // Stufe (Sport) erfasst: passt nicht ins alte Schema
        $sport = $this->fachAnlegen('Sport', 'SP', $this->fach, skala: 'stufe', zaehlt: false);
        $this->actingAs($user)->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $sport, 'pruefungsdatum' => now()->toDateString(), 'note_stufe' => 'B',
        ])->assertSessionHasNoErrors();
        $this->assertThrows(fn () => $migration->down(), \RuntimeException::class, 'Stufe');
        $this->assertTrue(Schema::hasColumn('noten', 'note_stufe'));
    }

    #[Test]
    public function bestehende_installation_ohne_bm_promotionsregel_bekommt_sie_per_migration(): void
    {
        $migration = require database_path('migrations/2026_10_01_000003_bms_promotionsregel.php');
        $regel = fn () => (array) DB::table('kategorien')->where('code', 'BMS')
            ->first(['promotion_min_schnitt', 'promotion_max_ungenuegend', 'promotion_max_minuspunkte']);

        DB::table('kategorien')->where('code', 'BMS')->update(['promotion_min_schnitt' => null, 'promotion_max_ungenuegend' => null, 'promotion_max_minuspunkte' => null]);
        $migration->up();
        $this->assertEquals(['promotion_min_schnitt' => 4.0, 'promotion_max_ungenuegend' => 2, 'promotion_max_minuspunkte' => 2.0], array_map('floatval', $regel()));

        // Eine eigene Regel des Admins bleibt stehen
        DB::table('kategorien')->where('code', 'BMS')->update(['promotion_min_schnitt' => null, 'promotion_max_ungenuegend' => 3, 'promotion_max_minuspunkte' => null]);
        $migration->up();
        $this->assertEquals(['promotion_min_schnitt' => null, 'promotion_max_ungenuegend' => 3, 'promotion_max_minuspunkte' => null], $regel());
    }

    #[Test]
    public function stufe_und_zahl_werden_je_nach_skala_verlangt(): void
    {
        [$user] = $this->lernender();
        $sport = $this->fachAnlegen('Sport', 'SP', $this->fach, skala: 'stufe', zaehlt: false);
        $this->actingAs($user);

        $this->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $sport, 'pruefungsdatum' => now()->toDateString(), 'note_wert' => '5.0',
        ])->assertSessionHasErrors('note_stufe');
        $this->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $this->englisch, 'pruefungsdatum' => now()->toDateString(), 'note_stufe' => 'A',
        ])->assertSessionHasErrors('note_wert');
        $this->post(route('learner.grades.store'), [
            'typ' => 'fach', 'fach_id' => $sport, 'pruefungsdatum' => now()->toDateString(), 'note_stufe' => 'X',
        ])->assertSessionHasErrors('note_stufe');

        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function stufe_verletzt_den_db_check_nicht_und_zahl_ausserhalb_der_skala_schon(): void
    {
        [, $id] = $this->lernender();
        $basis = ['lernender_id' => $id, 'kategorie_id' => $this->fach, 'semester_id' => DB::table('semester')->max('semester_id'),
            'fach_id' => $this->englisch, 'pruefungsdatum' => now()->toDateString(), 'erfasst_von_benutzer_id' => User::first()->benutzer_id];

        DB::table('noten')->insert($basis + ['note_wert' => null, 'note_stufe' => 'd']);
        $this->assertSame(1, DB::table('noten')->count());

        $this->expectException(QueryException::class);
        DB::table('noten')->insert($basis + ['note_wert' => null, 'note_stufe' => null]);
    }

    // ---------------------------------------------------------------------------------------------

    /** @return array{0: User, 1: int} */
    private function lernender(?int $lehrberuf = null): array
    {
        $user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf ?? $this->lehrberuf])->create();

        return [$user, (int) $user->lernender->lernender_id];
    }

    private function fachAnlegen(string $name, string $kurz, int $kategorie, string $skala = 'note', bool $zaehlt = true): int
    {
        $id = DB::table('faecher')->insertGetId(['name' => $name, 'kurzname' => $kurz, 'kategorie_id' => $kategorie,
            'skala' => $skala, 'zaehlt' => $zaehlt, 'aktiv' => 1]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $this->lehrberuf, 'fach_id' => $id]);
        Konfiguration::vergessen();

        return $id;
    }

    private function noten(User $user, ?float $schulmodul = null, ?float $uek = null, ?float $englisch = null, ?float $abu = null): void
    {
        $this->actingAs($user);
        $datum = now()->toDateString();
        foreach ([['modul', $this->schulmodul, $schulmodul], ['modul', $this->uekModul, $uek], ['fach', $this->englisch, $englisch], ['fach', $this->abuFach, $abu]] as [$typ, $bezug, $wert]) {
            if ($wert === null) {
                continue;
            }
            $this->post(route('learner.grades.store'), [
                'typ' => $typ, $typ.'_id' => $bezug, 'pruefungsdatum' => $datum, 'note_wert' => (string) $wert,
            ])->assertSessionHasNoErrors();
        }
    }

    /** @param  array<string, float>  $werte */
    private function positionen(User $user, int $lernenderId, array $werte): void
    {
        foreach ($werte as $code => $wert) {
            NotenbaumPosition::create([
                'lernender_id' => $lernenderId,
                'knoten_id' => DB::table('notenbaum_knoten')->where('code', $code)->value('knoten_id'),
                'note_wert' => $wert,
                'erfasst_von_benutzer_id' => $user->benutzer_id,
            ]);
        }
    }

    /** Baum laut LAGE.md §2, von Hand in die Tabellen geschrieben (der Vorlagen-Import hat eigene Tests). */
    private function baumAnlegen(): int
    {
        $baum = DB::table('notenbaeume')->insertGetId(['name' => 'Informatiker/in EFZ', 'bezug' => 'lehrberuf', 'lehrberuf_id' => $this->lehrberuf, 'aktiv' => true]);
        $knoten = function (array $d, ?int $eltern, int $sort) use ($baum, &$knoten) {
            $id = DB::table('notenbaum_knoten')->insertGetId([
                'baum_id' => $baum, 'eltern_id' => $eltern, 'code' => $d['code'], 'name' => $d['code'], 'typ' => $d['typ'],
                'gewicht' => $d['gewicht'] ?? 1, 'rundung' => $d['rundung'] ?? null, 'fallnote' => $d['fallnote'] ?? null,
                'kategorie_id' => $d['kategorie_id'] ?? null, 'elementtyp' => $d['elementtyp'] ?? 'alle', 'sortierung' => $sort,
            ]);
            foreach ($d['faecher'] ?? [] as $f) {
                DB::table('notenbaum_knoten_faecher')->insert(['knoten_id' => $id, 'fach_id' => $f]);
            }
            foreach ($d['kinder'] ?? [] as $i => $k) {
                $knoten($k, $id, $i);
            }
        };
        $knoten(['code' => 'qv', 'typ' => 'gruppe', 'rundung' => 0.1, 'fallnote' => 4.0, 'kinder' => [
            ['code' => 'ipa', 'typ' => 'manuell', 'gewicht' => 40, 'rundung' => 0.1, 'fallnote' => 4.0],
            ['code' => 'ab', 'typ' => 'gruppe', 'gewicht' => 20, 'rundung' => 0.1, 'kinder' => [
                ['code' => 'ab_erfahrung', 'typ' => 'kategorie', 'kategorie_id' => $this->abu, 'rundung' => 0.5],
                ['code' => 'ab_schlussarbeit', 'typ' => 'manuell'],
                ['code' => 'ab_schlusspruefung', 'typ' => 'manuell'],
            ]],
            ['code' => 'egk', 'typ' => 'faecher', 'faecher' => [$this->englisch], 'gewicht' => 10, 'rundung' => 0.5],
            ['code' => 'ik', 'typ' => 'gruppe', 'gewicht' => 30, 'rundung' => 0.1, 'fallnote' => 4.0, 'kinder' => [
                ['code' => 'ik_bfs', 'typ' => 'kategorie', 'kategorie_id' => $this->fach, 'elementtyp' => 'modul', 'gewicht' => 80],
                ['code' => 'ik_uek', 'typ' => 'kategorie', 'kategorie_id' => $this->uek, 'elementtyp' => 'modul', 'gewicht' => 20],
            ]],
        ]], null, 0);
        Konfiguration::vergessen();

        return $baum;
    }
}
