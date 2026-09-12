<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Notenimport in der Verwaltung: nur Admin darf für sichtbare Lernende importieren (App\Policies\LernenderPolicy::noteAnlegen). */
class NotenImportVerwaltungTest extends TestCase
{
    private User $lernenderUser;

    private int $modul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '431', 'titel' => 'Aufträge durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul, 'kategorie_id' => $kategorie['FACH']]);
        DB::table('semester')->insert(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->lernenderUser = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01', 'lehrende' => '2028-07-31'])->create();
    }

    #[Test]
    public function admin_liest_und_uebernimmt_einen_import(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M431;LB1;4,5;50%\n";

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.read', $lernenderId), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('admin.learners.grades.import.index', $lernenderId));

        $vorschau = session('notenimport.'.$lernenderId);
        $this->assertSame('modul:'.$this->modul, $vorschau['zeilen'][0]['bezug']);

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.apply', $lernenderId), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            ->assertRedirect(route('admin.learners.grades.index', $lernenderId))
            ->assertSessionHas('success');

        $this->assertSame(1, Note::count());
        $this->assertSame(4.5, (float) Note::sole()->note_wert);
        $this->assertNull(session('notenimport.'.$lernenderId));
    }

    #[Test]
    public function admin_kann_die_vorschau_verwerfen(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M431;LB1;4,5;50%\n";

        $this->actingAs($admin)->post(route('admin.learners.grades.import.read', $lernenderId), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);
        $this->assertNotNull(session('notenimport.'.$lernenderId));

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.discard', $lernenderId))
            ->assertRedirect(route('admin.learners.grades.import.index', $lernenderId));

        $this->assertNull(session('notenimport.'.$lernenderId));
        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function datei_ohne_erkennbare_noten_wird_abgewiesen(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.read', $lernenderId), [
                'datei' => UploadedFile::fake()->createWithContent('leer.csv', "Datum;Fach/Modul;Titel;Note;Gewicht\n"),
            ])
            ->assertSessionHasErrors('datei');

        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function berufsbildner_darf_weder_uebernehmen_noch_verwerfen(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $bb = User::factory()->berufsbildner()->create();
        DB::table('betreuungen')->insert(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernenderId, 'gueltig_von' => '2024-08-01']);

        $this->actingAs($bb)->post(route('trainer.learners.grades.import.discard', $lernenderId))->assertForbidden();
        $this->actingAs($bb)->post(route('trainer.learners.grades.import.apply', $lernenderId), ['zeilen' => json_encode([])])->assertForbidden();
    }
}
