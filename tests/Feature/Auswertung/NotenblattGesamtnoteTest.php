<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Das gedruckte Notenblatt nennt die Gesamtnote eines Lernenden mit Notenbaum wie Dashboard und Cockpit:
 * «QV-Prognose», bis alles erfasst ist, «QV-Gesamtnote» danach, bei Nichtbestehen mit Hinweis.
 */
class NotenblattGesamtnoteTest extends TestCase
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

    private function gesamtBereich(User $user): string
    {
        $html = $this->actingAs($user)->get(route('learner.grades.print'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<div class="gesamt">.*?<\/div>/s', $html, $treffer), 'Gesamtnote fehlt im Notenblatt');

        return trim(preg_replace('/\s+/', ' ', strip_tags($treffer[0])));
    }

    #[Test]
    public function notenblatt_nennt_die_gesamtnote_prognose_solange_positionen_fehlen(): void
    {
        [$user] = $this->lernender();
        $this->baumAnlegen();
        $this->noten($user, schulmodul: 5.0, uek: 4.0, englisch: 4.5, abu: 4.5);

        $bereich = $this->gesamtBereich($user);

        $this->assertStringContainsString('QV-Prognose', $bereich);
        $this->assertStringNotContainsString('Nicht bestanden', $bereich);
    }

    #[Test]
    public function notenblatt_weist_verletzte_fallnote_als_nicht_bestanden_aus(): void
    {
        [$user, $id] = $this->lernender();
        $this->baumAnlegen();
        $this->noten($user, schulmodul: 5.0, uek: 4.0, englisch: 4.5, abu: 4.5);
        $this->positionen($user, $id, ['ipa' => 3.5]);

        $bereich = $this->gesamtBereich($user);

        $this->assertStringContainsString('QV-Prognose', $bereich);
        $this->assertStringContainsString('Nicht bestanden', $bereich);
    }

    #[Test]
    public function notenblatt_heisst_gesamtnote_qv_gesamtnote_wenn_alles_erfasst_ist(): void
    {
        [$user, $id] = $this->lernender();
        $this->baumAnlegen();
        $this->noten($user, schulmodul: 5.0, uek: 4.0, englisch: 4.5, abu: 4.5);
        $this->positionen($user, $id, ['ipa' => 5.0, 'ab_schlussarbeit' => 5.0, 'ab_schlusspruefung' => 5.0]);

        $bereich = $this->gesamtBereich($user);

        $this->assertStringContainsString('QV-Gesamtnote', $bereich);
        $this->assertStringNotContainsString('Prognose', $bereich);
        $this->assertStringNotContainsString('Nicht bestanden', $bereich);
    }

    #[Test]
    public function notenblatt_ohne_notenbaum_bleibt_bei_gesamtnote(): void
    {
        [$user] = $this->lernender();
        $this->noten($user, schulmodul: 5.0, uek: 4.0, englisch: 4.5, abu: 4.5);

        $bereich = $this->gesamtBereich($user);

        $this->assertStringContainsString('Gesamtnote', $bereich);
        $this->assertStringNotContainsString('QV-', $bereich);
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
