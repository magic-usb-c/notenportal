<?php

declare(strict_types=1);

namespace Tests\Feature\Dokumente;

use App\Models\Aktivitaet;
use App\Models\Dokument;
use App\Models\ModulDokument;
use App\Models\Note;
use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Auswertung\Konfiguration;
use App\Services\Import\ZeugnisAbgleich;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Protokoll;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Zeugnis-Abgleich: Stufenfächer (Sport) werden nicht zur Übernahme angeboten, die Übernahme löst dieselben
 * Folgen aus wie der Notenimport, und eine fehlende Datei endet mit einer Meldung statt einem 500.
 */
class ZeugnisFolgenTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $user;

    private int $fach;

    private int $sport;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $this->fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK', 'zaehlt' => 1]);
        $this->sport = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sport', 'kurzname' => 'SPO', 'skala' => 'stufe', 'zaehlt' => 0]);
        DB::table('lehrberuf_faecher')->insert([['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->fach], ['lehrberuf_id' => $lehrberuf, 'fach_id' => $this->sport]]);
        $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create(['email' => 'lernender@firma.ch']);
    }

    private function zeugnisPdf(array $zeilen): UploadedFile
    {
        $inhalt = "BT\n/F1 12 Tf\n14 TL\n50 750 Td\n";
        foreach ($zeilen as $z) {
            $inhalt .= '('.str_replace(['(', ')'], ['\\(', '\\)'], $z).") Tj T*\n";
        }
        $inhalt .= 'ET';
        $o = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length '.strlen($inhalt)." >>\nstream\n".$inhalt."\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $off = [];
        foreach ($o as $i => $b) {
            $off[$i] = strlen($pdf);
            $pdf .= "$i 0 obj\n$b\nendobj\n";
        }
        $x = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($off as $v) {
            $pdf .= sprintf("%010d 00000 n \n", $v);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$x\n%%EOF\n";
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($tmp, $pdf);

        return new UploadedFile($tmp, 'zeugnis.pdf', 'application/pdf', null, true);
    }

    private function hochladen(User $user, array $zeilen = ['Zeugnis Fruehlingssemester 2026', 'Sprache und Kommunikation ........ 4,5', 'Sport ........ 5.0']): Dokument
    {
        $this->actingAs($user)->post(route('learner.documents.store'), ['datei' => $this->zeugnisPdf($zeilen), 'art' => 'zeugnis', 'semester_id' => $this->semester])
            ->assertSessionHasNoErrors();

        return Dokument::latest('dokument_id')->firstOrFail();
    }

    #[Test]
    public function stufenfach_mit_zahl_im_zeugnis_wird_nicht_zur_uebernahme_angeboten(): void
    {
        $d = $this->hochladen($this->user);

        $zeilen = app(ZeugnisAbgleich::class)->pruefen(Storage::disk('local')->path($d->pfad), (int) $this->user->lernender->lernender_id, $this->semester)['zeilen'];
        $status = array_column($zeilen, 'status', 'bezug');
        $this->assertSame('fehlt', $status['fach:'.$this->fach]);
        $this->assertSame('stufe', $status['fach:'.$this->sport]);

        $html = $this->get(route('learner.documents.reconcile', ['dokument_id' => $d->dokument_id, 'semester_id' => $this->semester]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '[uebernehmen]'), 'Nur das Sprachfach bietet «übernehmen» an.');
    }

    #[Test]
    public function uebernahme_mit_stufenfach_nimmt_die_gueltigen_zeilen_trotzdem_auf(): void
    {
        $d = $this->hochladen($this->user);

        $this->post(route('learner.documents.reconcile.apply', $d->dokument_id), ['semester_id' => $this->semester, 'zeilen' => [
            ['bezug' => 'fach:'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1'],
            ['bezug' => 'fach:'.$this->sport, 'note' => '5', 'uebernehmen' => '1'],
        ]])->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertSame(1, Note::count());
        $this->assertSame($this->fach, Note::sole()->fach_id);
    }

    #[Test]
    public function nur_stufenfach_markiert_meldet_keine_zeile_ausgewaehlt_ohne_roh_meldung(): void
    {
        $d = $this->hochladen($this->user);

        $this->post(route('learner.documents.reconcile.apply', $d->dokument_id), ['semester_id' => $this->semester, 'zeilen' => [
            ['bezug' => 'fach:'.$this->sport, 'note' => '5', 'uebernehmen' => '1'],
        ]])->assertSessionHas('error', 'Keine Zeile ausgewählt.');

        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function uebernahme_durch_lernende_unter_grenze_meldet_lernende_und_betreuer(): void
    {
        $bb = User::factory()->berufsbildner()->create(['email' => 'bb@firma.ch']);
        $this->betreue($bb, $this->user->lernender);
        $d = $this->hochladen($this->user);
        Notification::fake();

        $this->post(route('learner.documents.reconcile.apply', $d->dokument_id), ['semester_id' => $this->semester, 'zeilen' => [
            ['bezug' => 'fach:'.$this->fach, 'note' => '3', 'uebernehmen' => '1'],
        ]])->assertSessionHas('success');

        Notification::assertSentTo($this->user, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BELOW_THRESHOLD);
        Notification::assertSentTo($bb, PortalMail::class, fn (PortalMail $m) => $m->type === NotificationCatalog::BELOW_THRESHOLD);
        $this->assertDatabaseHas('notification_digest_items', ['user_id' => $bb->benutzer_id, 'type' => NotificationCatalog::GRADE_ADDED]);
    }

    #[Test]
    public function uebernahme_durch_verwaltung_wird_protokolliert_und_meldet_betreuer_nicht(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $this->user->lernender);
        $d = $this->hochladen($this->user);
        $admin = User::factory()->admin()->create();
        $lernenderId = (int) $this->user->lernender->lernender_id;

        $this->actingAs($admin)->post(route('admin.learners.documents.reconcile.apply', [$lernenderId, $d->dokument_id]), ['semester_id' => $this->semester, 'zeilen' => [
            ['bezug' => 'fach:'.$this->fach, 'note' => '4.5', 'uebernehmen' => '1'],
        ]])->assertSessionHas('success');

        $eintrag = Aktivitaet::where('aktion', Protokoll::ADMIN_NOTENIMPORT_UEBERNOMMEN)->first();
        $this->assertNotNull($eintrag);
        $this->assertDatabaseMissing('notification_digest_items', ['user_id' => $bb->benutzer_id, 'type' => NotificationCatalog::GRADE_ADDED]);
    }

    #[Test]
    public function fehlende_datei_beim_herunterladen_meldet_statt_500(): void
    {
        $d = $this->hochladen($this->user);
        Storage::disk('local')->delete($d->pfad);

        foreach (['', '?anzeigen=1'] as $query) {
            $this->from(route('learner.documents.index'))->get(route('learner.documents.show', $d->dokument_id).$query)
                ->assertRedirect(route('learner.documents.index'))
                ->assertSessionHas('error', 'Die Datei ist nicht mehr vorhanden.');
        }
    }

    #[Test]
    public function fehlende_modulunterlage_meldet_statt_500(): void
    {
        $modulId = (int) DB::table('module')->insertGetId(['modul_nummer' => 'M781', 'titel' => 'Unterlagen']);
        $this->actingAs($this->user)->post(route('modules.documents.store', $modulId), [
            'datei' => UploadedFile::fake()->create('skript.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $unterlage = ModulDokument::query()->firstOrFail();
        Storage::disk('local')->delete($unterlage->pfad);

        $this->from(route('modules.show', $modulId))->get(route('modules.documents.show', [$modulId, $unterlage->modul_dokument_id]))
            ->assertRedirect(route('modules.show', $modulId))
            ->assertSessionHas('error', 'Die Datei ist nicht mehr vorhanden.');
    }
}
