<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
}
