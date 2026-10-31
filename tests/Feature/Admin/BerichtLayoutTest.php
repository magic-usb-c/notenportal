<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Notenbericht: Histogramm füllt die Kartenhöhe, rechtsbündige Spaltenköpfe bleiben über den Zahlen. */
class BerichtLayoutTest extends TestCase
{
    private int $semesterId = 0;

    private function berichtMitNoten(array $parameter = ['semester' => 'alle'], bool $mitSemester = false): string
    {
        $admin = User::factory()->admin()->create();
        $kategorie = (int) Kategorie::where('code', 'FACH')->value('kategorie_id');
        $semester = Semester::factory()->create(['start_datum' => '2020-08-01', 'end_datum' => '2021-01-31']);
        $this->semesterId = (int) $semester->semester_id;
        $user = User::factory()->lernender([
            'lehrbeginn' => now()->subMonths(18)->toDateString(),
            'lehrende' => now()->addMonths(30)->toDateString(),
        ])->create();
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kategorie]);
        DB::table('noten')->insert([
            'lernender_id' => $user->lernender->lernender_id, 'kategorie_id' => $kategorie, 'semester_id' => $semester->semester_id,
            'fach_id' => $fach->fach_id, 'modul_belegung_id' => null, 'titel' => 'Note', 'pruefungsdatum' => '2020-08-20',
            'note_wert' => 4.5, 'gewichtung_prozent' => 100, 'erfasst_von_benutzer_id' => $user->benutzer_id,
            'erstellt_am' => now()->toDateTimeString(), 'geloescht_am' => null,
        ]);

        if ($mitSemester) {
            $parameter['semester'] = $this->semesterId;
        }

        return $this->actingAs($admin)->get(route('admin.reports.grades', $parameter))->assertOk()->getContent();
    }

    #[Test]
    public function histogramm_waechst_mit_der_kartenhoehe_statt_feste_hoehe_zu_haben(): void
    {
        $html = $this->berichtMitNoten();
        $start = (int) strpos($html, 'role="img" aria-labelledby="np-histogramm-satz"');
        $this->assertGreaterThan(0, $start);
        $histogramm = substr($html, $start - 60, (int) strpos($html, 'id="np-histogramm-satz"') - $start + 60);

        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])h-64\b/', $histogramm, 'keine feste Höhe an Achse oder Balkenfläche');
        $this->assertMatchesRegularExpression('/<div class="flex h-full flex-col">\s*<div class="mb-3 flex flex-wrap/', substr($html, $start - 600, 700), 'Slot-Inhalt füllt die Karte');
        $this->assertMatchesRegularExpression('/<div class="flex min-h-64 flex-1 gap-2" role="img"/', $html.'', 'Diagramm nimmt den Rest, mindestens 16rem');
    }

    #[Test]
    public function rechtsbuendige_spaltenkoepfe_setzen_den_pfeil_vor_den_text(): void
    {
        $html = $this->berichtMitNoten(['semester' => 'alle', 'sort' => 'gesamt', 'dir' => 'desc']);

        foreach (['gesamt', 'ungenuegend', 'pruefungen', 'letzte'] as $spalte) {
            $this->assertMatchesRegularExpression('/<th scope="col" class="w-\d+ text-right" aria-sort="[a-z]+"><a href="[^"]*sort='.$spalte.'[^"]*" class="[^"]*flex-row-reverse[^"]*">/', $html, $spalte);
        }
        $this->assertMatchesRegularExpression('/<th scope="col" class="w-56" aria-sort="[a-z]+"><a href="[^"]*" class="(?![^"]*flex-row-reverse)[^"]*">'.preg_quote(e(__('Name')), '/').'/', $html, 'Name bleibt linksbündig');
    }

    #[Test]
    public function semesterspalte_rechtsbuendig_mit_pfeil_vor_dem_text(): void
    {
        $html = $this->berichtMitNoten([], true);
        $this->assertMatchesRegularExpression('/aria-sort="[a-z]+"><a href="[^"]*sort=semester[^"]*" class="[^"]*flex-row-reverse/', $html);
    }
}
