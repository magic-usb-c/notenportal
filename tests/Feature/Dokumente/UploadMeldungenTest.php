<?php

declare(strict_types=1);

namespace Tests\Feature\Dokumente;

use App\Models\User;
use App\Services\Dokumente\Ablage;
use App\Services\Dokumente\Modulablage;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Upload-Grenzen und Fehlermeldungen von Dokumenten und Modul-Unterlagen.
 *
 * Die App-Grenze muss unter der PHP-Grenze aus public/.htaccess liegen, sonst scheitert ein Upload
 * dazwischen mit einer Meldung ohne Grössenangabe. Und wer eine Datei mit passender Endung, aber
 * falschem Inhalt hochlädt, soll lesen, was erlaubt ist – keine rohe Liste von Medientypen.
 */
class UploadMeldungenTest extends TestCase
{
    private int $modulId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        Storage::fake('local');
        $this->modulId = (int) DB::table('module')->insertGetId(['modul_nummer' => 'M779', 'titel' => 'Unterlagen']);
    }

    private function datei(string $name, string $inhalt): UploadedFile
    {
        $pfad = tempnam(sys_get_temp_dir(), 'np');
        file_put_contents($pfad, $inhalt);

        return new UploadedFile($pfad, $name, null, null, true);
    }

    private function phpGrenzeKb(): int
    {
        preg_match('/upload_max_filesize\s+(\d+)M/', (string) file_get_contents(base_path('public/.htaccess')), $m);

        return (int) $m[1] * 1024;
    }

    #[Test]
    public function modulgrenze_liegt_unter_der_php_grenze_und_gleich_wie_bei_dokumenten(): void
    {
        $this->assertLessThanOrEqual($this->phpGrenzeKb(), Modulablage::MAX_KB);
        $this->assertSame(Ablage::MAX_KB, Modulablage::MAX_KB);
    }

    #[Test]
    public function zu_grosse_modulunterlage_meldet_die_grenze_in_megabyte(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('modules.documents.store', $this->modulId), [
            'datei' => UploadedFile::fake()->create('gross.pdf', Modulablage::MAX_KB + 1, 'application/pdf'),
        ])->assertSessionHasErrors('datei');

        $meldung = session('errors')->first('datei');
        $this->assertStringContainsString('10 MB', $meldung);
        $this->assertStringNotContainsString('Kilobyte', $meldung);
        $this->assertSame(0, DB::table('modul_dokumente')->count());
    }

    #[Test]
    public function unterlage_an_der_grenze_wird_angenommen(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('modules.documents.store', $this->modulId), [
            'datei' => UploadedFile::fake()->create('knapp.pdf', Modulablage::MAX_KB, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('modul_dokumente')->count());
    }

    #[Test]
    public function upload_ueber_der_php_grenze_meldet_zu_gross_statt_einer_allgemeinen_meldung(): void
    {
        $user = User::factory()->lernender()->create();
        $datei = new UploadedFile(__FILE__, 'gross.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($user)->post(route('modules.documents.store', $this->modulId), ['datei' => $datei])
            ->assertSessionHasErrors('datei');

        $meldung = session('errors')->first('datei');
        $this->assertStringContainsString('zu gross', $meldung);
        $this->assertStringNotContainsString('validation.', $meldung);
        $this->assertStringNotContainsString('MB', $meldung);
        $this->assertSame(0, DB::table('modul_dokumente')->count());
    }

    #[Test]
    public function falscher_dateiinhalt_im_dokument_zeigt_erlaubte_arten_in_worten(): void
    {
        $user = User::factory()->lernender()->create();

        foreach (['leer.pdf' => '', 'seite.csv' => '<html><body>x</body></html>'] as $name => $inhalt) {
            $this->actingAs($user)->post(route('learner.documents.store'), [
                'datei' => $this->datei($name, $inhalt), 'art' => 'zeugnis',
            ])->assertSessionHasErrors('datei');

            $meldung = session('errors')->first('datei');
            $this->assertStringNotContainsString('application/', $meldung, $name);
            $this->assertStringNotContainsString('image/', $meldung, $name);
            $this->assertStringContainsString('PDF', $meldung, $name);
            $this->app['session.store']->flush();
        }

        $this->assertDatabaseCount('dokumente', 0);
    }

    #[Test]
    public function falscher_dateiinhalt_bei_modulunterlage_zeigt_erlaubte_arten_in_worten(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('modules.documents.store', $this->modulId), [
            'datei' => $this->datei('modul.pdf', '<html><body>x</body></html>'),
        ])->assertSessionHasErrors('datei');

        $meldung = session('errors')->first('datei');
        $this->assertStringNotContainsString('application/', $meldung);
        $this->assertStringContainsString('PDF', $meldung);
        $this->assertSame(0, DB::table('modul_dokumente')->count());
    }

    #[Test]
    public function nicht_erlaubte_endung_nennt_die_erlaubten_arten_in_worten(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('learner.documents.store'), [
            'datei' => $this->datei('skript.exe', 'MZ'), 'art' => 'zeugnis',
        ])->assertSessionHasErrors('datei');

        $meldung = session('errors')->first('datei');
        $this->assertStringContainsString('Endung', $meldung);
        $this->assertStringContainsString('PDF', $meldung);
        $this->assertStringNotContainsString('application/', $meldung);
    }

    #[Test]
    public function gueltige_dokumente_bleiben_erlaubt(): void
    {
        $user = User::factory()->lernender()->create();

        $this->actingAs($user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 20, 'application/pdf'), 'art' => 'zeugnis',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('dokumente', 1);
    }
}
