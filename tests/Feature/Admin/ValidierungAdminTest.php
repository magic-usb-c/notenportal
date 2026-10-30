<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Fach;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Dokumente\Ablage;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Eingabegrenzen und Meldungstexte in Stammdaten, Einrichtung, Prüfungstermine und Upload-Regeln. */
class ValidierungAdminTest extends TestCase
{
    use VerwaltungTestHilfen;

    private function admin(): User
    {
        return User::factory()->admin()->create(['passwort_wechsel_noetig' => false]);
    }

    private function kategorieDaten(array $ueberschreiben = []): array
    {
        return [...[
            'code' => 'XZ', 'name' => 'Extra', 'sortierung' => 5,
            'rundung_element' => '0.1', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => 1,
        ], ...$ueberschreiben];
    }

    private function semesterDaten(array $ueberschreiben = []): array
    {
        return [...[
            'bezeichnung' => '30/31-1', 'start_datum' => '2030-08-01', 'end_datum' => '2031-01-31', 'sortierung' => 10,
        ], ...$ueberschreiben];
    }

    #[Test]
    public function semester_mit_zu_grosser_sortierung_gibt_validierungsfehler_statt_500(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/master-data/semesters', $this->semesterDaten(['sortierung' => 70000]))
            ->assertSessionHasErrors('sortierung');
        $this->assertSame(0, DB::table('semester')->where('bezeichnung', '30/31-1')->count());

        $this->post('/admin/master-data/semesters', $this->semesterDaten(['sortierung' => 65535]))
            ->assertSessionHasNoErrors();
        $id = (int) DB::table('semester')->where('bezeichnung', '30/31-1')->value('semester_id');

        $this->put("/admin/master-data/semesters/{$id}", $this->semesterDaten(['sortierung' => 65536]))
            ->assertSessionHasErrors('sortierung');
    }

    #[Test]
    public function kategorie_mit_zu_grosser_sortierung_oder_zu_grossem_gesamtgewicht_gibt_validierungsfehler(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/master-data/categories', $this->kategorieDaten(['sortierung' => 70000]))
            ->assertSessionHasErrors('sortierung');
        $this->post('/admin/master-data/categories', $this->kategorieDaten(['gewicht_gesamt' => 1000]))
            ->assertSessionHasErrors('gewicht_gesamt');
        $this->assertSame(0, DB::table('kategorien')->where('code', 'XZ')->count());

        $this->post('/admin/master-data/categories', $this->kategorieDaten(['gewicht_gesamt' => 999.99, 'sortierung' => 65535]))
            ->assertSessionHasNoErrors();
        $id = (int) DB::table('kategorien')->where('code', 'XZ')->value('kategorie_id');

        $this->put("/admin/master-data/categories/{$id}", $this->kategorieDaten(['sortierung' => 70000]))
            ->assertSessionHasErrors('sortierung');
        $this->put("/admin/master-data/categories/{$id}", $this->kategorieDaten(['gewicht_gesamt' => 1000]))
            ->assertSessionHasErrors('gewicht_gesamt');
    }

    #[Test]
    public function einrichtung_kategorien_mit_zu_grossem_gesamtgewicht_gibt_validierungsfehler(): void
    {
        $this->seed(BasisSeeder::class);
        $kategorien = DB::table('kategorien')->get()->mapWithKeys(fn ($k) => [$k->kategorie_id => [
            'name' => $k->name, 'aktiv' => 1, 'rundung_element' => '0.5', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => 1000,
        ]])->all();

        $this->actingAs($this->admin())->post(route('admin.setup.categories'), ['kategorien' => $kategorien])
            ->assertSessionHasErrors();
    }

    #[Test]
    #[DataProvider('einrichtungsListen')]
    public function einrichtung_lehnt_email_adressen_ab_die_sich_nur_in_der_schreibweise_unterscheiden(string $route, array $zeile, string $schluessel): void
    {
        $this->seed(BasisSeeder::class);
        $mail = fn (string $e) => [...$zeile, 'email' => $e];
        $lehrberuf = (int) DB::table('lehrberufe')->where('kuerzel', 'INAP')->value('lehrberuf_id');
        $zeilen = array_map(fn ($z) => isset($z['lehrberuf_id']) ? [...$z, 'lehrberuf_id' => $lehrberuf] : $z, [$mail('anna@firma.ch'), $mail('Anna@firma.ch')]);

        $this->actingAs($this->admin())->post(route($route), [$schluessel => $zeilen])
            ->assertSessionHasErrors(["{$schluessel}.0.email", "{$schluessel}.1.email"]);
        $this->assertSame(0, User::where('email', 'anna@firma.ch')->count());
    }

    public static function einrichtungsListen(): array
    {
        return [
            'Personen' => ['admin.setup.people', ['vorname' => 'A', 'nachname' => 'B', 'rolle' => 'Berufsbildner'], 'personen'],
            'Lernende' => ['admin.setup.learners', ['vorname' => 'A', 'nachname' => 'B', 'lehrberuf_id' => 1, 'lehrbeginn' => '2026-08-01'], 'lernende'],
        ];
    }

    #[Test]
    public function kopfzaehler_der_pruefungsgruppe_zaehlt_abgesagte_nicht_als_fehlende_note(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $l = $this->neuerLernender();
        $this->betreue($bb, $l);
        $fach = Fach::factory()->create();
        foreach ([['Echt fehlend', null], ['Abgesagt', now()]] as [$titel, $abgesagt]) {
            Pruefung::create([
                'lernender_id' => $l->lernender_id, 'fach_id' => $fach->fach_id, 'titel' => $titel,
                'datum' => today()->subDays(3)->toDateString(), 'gewichtung_prozent' => 100, 'quelle' => 'manuell', 'abgesagt_am' => $abgesagt,
            ]);
        }

        $html = $this->actingAs($bb)->get('/trainer/exams?lernender_id='.$l->lernender_id)->assertOk()->getContent();

        preg_match('/Kürzlich vergangen.*?<\/h2>/s', $html, $m);
        $kopf = trim(preg_replace('/\s+/', ' ', strip_tags($m[0] ?? '')));
        $this->assertStringContainsString('1 Note fehlt', $kopf);
        $this->assertStringNotContainsString('2 Note fehlt', $kopf);
        $this->assertStringContainsString('Abgesagt', $html, 'Die abgesagte Prüfung bleibt in der Liste sichtbar.');
    }

    #[Test]
    public function kopfzaehler_der_pruefungsgruppe_entfaellt_wenn_nur_abgesagte_vergangen_sind(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $l = $this->neuerLernender();
        $this->betreue($bb, $l);
        Pruefung::create([
            'lernender_id' => $l->lernender_id, 'fach_id' => Fach::factory()->create()->fach_id, 'titel' => 'Nur abgesagt',
            'datum' => today()->subDays(3)->toDateString(), 'gewichtung_prozent' => 100, 'quelle' => 'manuell', 'abgesagt_am' => now(),
        ]);

        $html = $this->actingAs($bb)->get('/trainer/exams?lernender_id='.$l->lernender_id)->assertOk()->getContent();

        preg_match('/Kürzlich vergangen.*?<\/h2>/s', $html, $m);
        $this->assertStringNotContainsString('Note fehlt', $m[0] ?? '');
    }

    #[Test]
    public function upload_mit_nicht_erlaubter_endung_zeigt_deutschen_text_statt_rohem_schluessel(): void
    {
        $v = validator(
            ['datei' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')],
            Ablage::regeln(),
        );

        $this->assertTrue($v->fails());
        foreach ($v->errors()->all() as $meldung) {
            $this->assertStringNotContainsString('validation.', $meldung);
        }
        $this->assertStringContainsString('Endung', implode(' ', $v->errors()->all()));
    }

    #[Test]
    public function fehlende_validierungsnamen_der_rechner_felder_sind_uebersetzt(): void
    {
        $this->assertSame('Zielwert', trans('validation.attributes.zielwert'));
        $this->assertNotSame('validation.extensions', trans('validation.extensions'));
    }

    #[Test]
    public function validierungsnamen_der_rechner_felder_sind_auch_englisch_uebersetzt(): void
    {
        $vorher = app()->getLocale();
        app()->setLocale('en');

        try {
            $namen = trans('validation.attributes');
            foreach (['zielwert', 'zeilen.*.typ', 'zeilen.*.fach_id', 'zeilen.*.modul_id'] as $feld) {
                $this->assertNotEmpty($namen[$feld] ?? null, $feld);
            }
            $this->assertSame('target value', $namen['zielwert']);
        } finally {
            app()->setLocale($vorher);
        }
    }
}
