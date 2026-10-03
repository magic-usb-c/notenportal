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

    private int $semesterId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->modul = DB::table('module')->insertGetId(['modul_nummer' => '908', 'titel' => 'Testmodul Theta durchführen']);
        DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberuf, 'modul_id' => $this->modul, 'kategorie_id' => $kategorie['FACH']]);
        $this->semesterId = (int) DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->lernenderUser = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
    }

    #[Test]
    public function admin_liest_und_uebernimmt_einen_import(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M908;LB1;4,5;50%\n";

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.read', $lernenderId), ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)])
            ->assertRedirect(route('admin.learners.grades.import.index', $lernenderId));

        $vorschau = session('notenimport.'.$lernenderId);
        $this->assertSame('modul:'.$this->modul, $vorschau['zeilen'][0]['bezug']);

        $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.apply', $lernenderId), ['zeilen' => json_encode($vorschau['zeilen']), 'token' => $vorschau['token']])
            // Alle Zeilen liegen in einem Semester → die Notenliste öffnet genau dort
            ->assertRedirect(route('admin.learners.grades.index', [$lernenderId, 'semester_id' => $this->semesterId]))
            ->assertSessionHas('success');

        $this->assertSame(1, Note::count());
        $this->assertSame(4.5, (float) Note::sole()->note_wert);
        $this->assertNull(session('notenimport.'.$lernenderId));
    }

    #[Test]
    public function admin_kann_korrigierte_zeilen_erneut_pruefen_lassen(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n09.03.2026;Sprache;Vortrag;5.0;\n";

        $this->actingAs($admin)->post(route('admin.learners.grades.import.read', $lernenderId),
            ['datei' => UploadedFile::fake()->createWithContent('noten.csv', $csv)]);

        $zeile = session('notenimport.'.$lernenderId)['zeilen'][0];
        $this->assertSame('fehler', $zeile['status'], 'Ohne passendes Fach ist die Zeile nicht zuordenbar.');

        // Der Bezug wird von Hand auf ein Modul des Lehrberufs gesetzt; das Prüfen bestätigt die
        // Korrektur, ohne zu speichern – derselbe Weg wie beim Lernenden, nur über die Verwaltung.
        $antwort = $this->actingAs($admin)
            ->post(route('admin.learners.grades.import.validate', $lernenderId),
                ['zeilen' => json_encode([[...$zeile, 'bezug' => 'modul:'.$this->modul, 'uebernehmen' => true]])])
            ->assertOk()->json();

        $this->assertSame('ok', $antwort['zeilen'][0]['status']);
        $this->assertSame('ok', session('notenimport.'.$lernenderId)['zeilen'][0]['status']);
    }

    #[Test]
    public function admin_kann_die_vorschau_verwerfen(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $admin = User::factory()->admin()->create();
        $csv = "Datum;Fach/Modul;Titel;Note;Gewicht\n02.03.2026;M908;LB1;4,5;50%\n";

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
    public function berufsbildner_darf_den_import_nicht_bedienen(): void
    {
        $lernenderId = (int) $this->lernenderUser->lernender->lernender_id;
        $bb = User::factory()->berufsbildner()->create();
        DB::table('betreuungen')->insert(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernenderId, 'gueltig_von' => '2024-08-01']);

        $this->actingAs($bb)->post(route('trainer.learners.grades.import.discard', $lernenderId))->assertForbidden();
        $this->actingAs($bb)->post(route('trainer.learners.grades.import.apply', $lernenderId), ['zeilen' => json_encode([])])->assertForbidden();
        // Auch das blosse Prüfen ist gesperrt: die Berechtigung hängt am Anlegen von Noten,
        // und die Antwort verriete sonst Fach- und Modulzuordnungen einer fremden Person.
        $this->actingAs($bb)->post(route('trainer.learners.grades.import.validate', $lernenderId), ['zeilen' => json_encode([])])->assertForbidden();
    }
}
