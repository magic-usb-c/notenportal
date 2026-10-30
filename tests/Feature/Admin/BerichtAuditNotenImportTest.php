<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BerichtAuditNotenImportTest extends TestCase
{
    /** @return array{semester: Semester, lehrberuf: int} */
    private function mitLernenden(array $lernende): array
    {
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $kategorie = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        DB::table('kategorien')->where('kategorie_id', $kategorie)->update(['promotion_min_schnitt' => 4.0]);
        Konfiguration::vergessen();
        $semester = Semester::factory()->create(['start_datum' => '2020-08-01', 'end_datum' => '2021-01-31']);

        foreach ($lernende as [$nachname, $monate, $wert]) {
            $user = User::factory()->lernender([
                'lehrberuf_id' => $lehrberuf,
                'lehrbeginn' => now()->subMonths($monate)->toDateString(),
                'lehrende' => now()->addMonths(48 - $monate)->toDateString(),
            ])->create(['nachname' => $nachname]);
            $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kategorie]);
            DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
            DB::table('noten')->insert([
                'lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $kategorie, 'semester_id' => $semester->semester_id,
                'fach_id' => $fach->fach_id, 'modul_belegung_id' => null, 'titel' => 'Note', 'pruefungsdatum' => '2020-08-20',
                'note_wert' => $wert, 'gewichtung_prozent' => 100, 'erfasst_von_benutzer_id' => $user->benutzer_id,
                'erstellt_am' => now()->toDateTimeString(), 'geloescht_am' => null,
            ]);
        }

        return ['semester' => $semester, 'lehrberuf' => $lehrberuf];
    }

    #[Test]
    public function kachel_kritisch_traegt_das_gewaehlte_lehrjahr_in_die_lernendenliste(): void
    {
        $admin = User::factory()->admin()->create();

        $mit = route('admin.learners.index', ['warnung' => 'kritisch', 'lehrjahr' => 2]);
        $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle', 'lehrjahr' => 2]))
            ->assertOk()
            ->assertSee('href="'.e($mit).'"', false);

        $ohne = route('admin.learners.index', ['warnung' => 'kritisch']);
        $this->get(route('admin.reports.grades', ['semester' => 'alle']))
            ->assertOk()
            ->assertSee('href="'.e($ohne).'"', false);
    }

    #[Test]
    public function kachel_promotion_gefaehrdet_fuehrt_zu_den_betroffenen_lernenden(): void
    {
        $admin = User::factory()->admin()->create();
        ['semester' => $semester] = $this->mitLernenden([['Aaaunbedenklich', 18, 5.0], ['Zzzgefaehrdet', 18, 3.0]]);
        $sid = $semester->semester_id;

        $html = $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => $sid]))
            ->assertOk();
        $html->assertSee('sort=gefaehrdet&amp;dir=desc#lernende"', false);

        // Sortiert nach «gefährdet»: der betroffene Lernende steht vor dem alphabetisch früheren, und nur er trägt das Etikett
        $inhalt = $this->get(route('admin.reports.grades', ['semester' => $sid, 'sort' => 'gefaehrdet', 'dir' => 'desc']))
            ->assertOk()
            ->assertSee('href="#lernende"', false)
            ->getContent();
        $tabelle = substr($inhalt, (int) strpos($inhalt, 'id="lernende"'));
        $this->assertLessThan(strpos($tabelle, 'Aaaunbedenklich'), strpos($tabelle, 'Zzzgefaehrdet'));
        $this->assertSame(1, substr_count($tabelle, 'data-promotion-gefaehrdet'));
    }

    #[Test]
    public function ohne_semester_gibt_es_weder_etikett_noch_gefaehrdungs_kachel(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mitLernenden([['Zzzgefaehrdet', 18, 3.0]]);

        $this->actingAs($admin)
            ->get(route('admin.reports.grades', ['semester' => 'alle', 'sort' => 'gefaehrdet']))
            ->assertOk()
            ->assertDontSee('data-promotion-gefaehrdet', false);
    }

    #[Test]
    public function importvorschau_zeigt_die_meldung_ganz_unter_dem_statuswort(): void
    {
        $lernender = User::factory()->lernender()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;Unbekannt;LB1;4,5;50\n";
        $this->actingAs($lernender)
            ->post(route('learner.grades.import.read'), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('learner.grades.import.index'));

        $html = $this->get(route('learner.grades.import.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-import-meldung', $html);
        $this->assertStringNotContainsString('x-text="z.meldung ||', $html, 'Meldung steht nicht mehr gekürzt in der Marke');
        $this->assertStringNotContainsString(':title="z.meldung', $html, 'Volltext nicht nur im title');
    }

    #[Test]
    public function drehende_ladeanzeigen_werden_unter_reduzierter_bewegung_zur_ueberblendung(): void
    {
        $dateien = [
            'import/index', 'admin/betrieb/_testmail', 'admin/betrieb/_kopie', 'admin/betrieb/_logo', 'admin/betrieb/edit',
        ];
        $gefunden = 0;
        foreach ($dateien as $datei) {
            foreach (file(resource_path("views/{$datei}.blade.php")) as $nr => $zeile) {
                if (str_contains($zeile, 'animate-spin')) {
                    $gefunden++;
                    $this->assertStringContainsString('ruhig:animate-pulse', $zeile, "{$datei}:".($nr + 1));
                }
            }
        }
        // Sicherungskopie und Logo: je Knopf eine eigene Anzeige (Testen/Kopieren, Entfernen/Hochladen)
        $this->assertSame(9, $gefunden);
    }
}
