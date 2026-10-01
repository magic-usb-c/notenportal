<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DokumenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function fehlgeschlagenes_speichern_legt_keinen_datensatz_an_und_meldet_fehler(): void
    {
        $user = User::factory()->lernender()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->andReturn(false);
        Storage::set('local', $disk);

        $this->actingAs($user)->from(route('learner.documents.index'))->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'),
            'art' => 'zeugnis',
        ])->assertRedirect(route('learner.documents.index'))->assertSessionHas('error');

        $this->assertDatabaseCount('dokumente', 0);
    }

    #[Test]
    public function dokumente_liegen_privat_und_sind_nur_fuer_berechtigte_erreichbar(): void
    {
        $user = User::factory()->lernender()->create(['nachname' => 'Huber', 'vorname' => 'Nina']);
        $lernenderId = (int) $user->lernender->lernender_id;

        $this->actingAs($user)->post(route('learner.documents.store'), [
            'datei' => UploadedFile::fake()->create('scan 2026.pdf', 120, 'application/pdf'),
            'art' => 'zeugnis',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $dokument = Dokument::firstOrFail();
        $this->assertStringStartsWith('lernende/'.$lernenderId.'/dokumente/', $dokument->pfad);
        $this->assertSame('scan 2026', $dokument->titel);
        Storage::disk('local')->assertExists($dokument->pfad);

        $this->get(route('learner.documents.index'))->assertOk()->assertSee('scan 2026');
        $this->get(route('learner.documents.show', $dokument->dokument_id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('huber_nina_zeugnis.pdf');

        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('learner.documents.show', $dokument->dokument_id))->assertNotFound();

        $bb = User::factory()->berufsbildner()->create();
        $this->actingAs($bb)->get(route('trainer.learners.documents.show', [$lernenderId, $dokument->dokument_id]))->assertNotFound();
        DB::table('betreuungen')->insert(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id, 'lernender_id' => $lernenderId,
            'gueltig_von' => now()->subMonth()->toDateString()]);
        $this->get(route('trainer.learners.documents.index', $lernenderId))->assertOk()->assertSee('scan 2026');

        $this->actingAs($user)->delete(route('learner.documents.destroy', $dokument->dokument_id))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($dokument->pfad);
        $this->assertSame(0, Dokument::count());
    }

    #[Test]
    public function fremde_uploads_darf_der_lernende_nicht_loeschen(): void
    {
        $user = User::factory()->lernender()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.learners.documents.store', $user->lernender->lernender_id), [
            'datei' => UploadedFile::fake()->create('zeugnis.pdf', 50, 'application/pdf'), 'art' => 'zeugnis', 'titel' => 'Zeugnis HS',
        ])->assertSessionHasNoErrors();

        $dokument = Dokument::firstOrFail();
        $this->actingAs($user)->delete(route('learner.documents.destroy', $dokument->dokument_id))->assertForbidden();
        Storage::disk('local')->assertExists($dokument->pfad);
    }

    #[Test]
    public function verwaltung_zeigt_und_loescht_dokumente_nur_fuer_aktiv_betreute(): void
    {
        $lernender = User::factory()->lernender()->create()->lernender;
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.learners.documents.store', $lernender->lernender_id), [
            'datei' => UploadedFile::fake()->create('beleg.pdf', 40, 'application/pdf'), 'art' => 'sonstiges', 'titel' => 'Beleg',
        ])->assertSessionHasNoErrors();
        $dokument = Dokument::firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.learners.documents.show', [$lernender->lernender_id, $dokument->dokument_id]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $bb = User::factory()->berufsbildner()->create();
        $this->actingAs($bb)
            ->get(route('trainer.learners.documents.show', [$lernender->lernender_id, $dokument->dokument_id]))
            ->assertNotFound();
        $this->actingAs($bb)
            ->delete(route('trainer.learners.documents.destroy', [$lernender->lernender_id, $dokument->dokument_id]))
            ->assertNotFound();
        Storage::disk('local')->assertExists($dokument->pfad);

        $this->actingAs($admin)
            ->delete(route('admin.learners.documents.destroy', [$lernender->lernender_id, $dokument->dokument_id]))
            ->assertSessionHas('success');
        Storage::disk('local')->assertMissing($dokument->pfad);
        $this->assertSame(0, Dokument::count());
    }

    #[Test]
    public function nur_erlaubte_dateitypen_werden_angenommen(): void
    {
        $this->actingAs(User::factory()->lernender()->create());

        foreach ([['boese.php', 'text/x-php'], ['seite.html', 'text/html'], ['tarnung.pdf', 'text/html']] as [$name, $mime]) {
            $this->post(route('learner.documents.store'), ['datei' => UploadedFile::fake()->create($name, 5, $mime), 'art' => 'sonstiges'])
                ->assertSessionHasErrors('datei');
        }
        $this->assertSame(0, Dokument::count());
    }

    #[Test]
    public function ein_pdf_zeugnis_bietet_den_abgleich_nur_in_der_eigenen_ablage(): void
    {
        // Den Abgleich gibt es nur unter learner.*; früher suchte die Verwaltungsansicht eine Route, die fehlt (500)
        $user = User::factory()->lernender()->create();
        $lernenderId = (int) $user->lernender->lernender_id;
        $dokumentId = DB::table('dokumente')->insertGetId([
            'lernender_id' => $lernenderId, 'art' => 'zeugnis', 'titel' => 'Zeugnis 1. Semester', 'originalname' => 'z.pdf',
            'pfad' => 'lernende/'.$lernenderId.'/dokumente/z.pdf', 'mime' => 'application/pdf', 'groesse' => 2048,
            'sha256' => str_repeat('a', 64), 'hochgeladen_von_benutzer_id' => $user->benutzer_id, 'erstellt_am' => now(), 'aktualisiert_am' => now(),
        ]);
        $abgleich = route('learner.documents.reconcile', $dokumentId);

        $this->actingAs($user)->get(route('learner.documents.index'))
            ->assertOk()
            ->assertSee($abgleich, false)
            ->assertSee('dokument-hochladen', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.learners.documents.index', $lernenderId))
            ->assertOk()
            ->assertSee('Zeugnis 1. Semester')
            ->assertDontSee('reconcile', false);
    }

    #[Test]
    public function ein_fehler_beim_hochladen_oeffnet_das_sheet_wieder(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->from(route('learner.documents.index'))
            ->post(route('learner.documents.store'), ['art' => 'zeugnis'])
            ->assertSessionHasErrors('datei');

        $this->get(route('learner.documents.index'))
            ->assertOk()
            ->assertSee('id="datei-fehler"', false)
            ->assertSee('show: true', false);
    }
}
