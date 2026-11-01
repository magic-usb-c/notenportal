<?php

declare(strict_types=1);

namespace Tests\Feature\Dokumente;

use App\Models\Dokument;
use App\Models\Note;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use Database\Seeders\BasisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/** Eine abweichende Zeile im Zeugnis-Abgleich führt zu den Noten des gewählten Semesters. */
class ZeugnisAbgleichNotenLinkTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $user;

    private int $semester;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BasisSeeder::class);
        $kategorie = DB::table('kategorien')->pluck('kategorie_id', 'code');
        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'TST', 'name' => 'Test EFZ']);
        $fach = DB::table('faecher')->insertGetId(['kategorie_id' => $kategorie['ABU'], 'name' => 'Sprache und Kommunikation', 'kurzname' => 'SK', 'zaehlt' => 1]);
        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf, 'fach_id' => $fach]);
        $this->semester = DB::table('semester')->insertGetId(['bezeichnung' => '25/26-2', 'start_datum' => '2026-02-01', 'end_datum' => '2026-07-31', 'sortierung' => 10]);
        Konfiguration::vergessen();
        $this->user = User::factory()->lernender(['lehrberuf_id' => $lehrberuf, 'lehrbeginn' => '2024-08-01'])->create();
        Note::factory()->create([
            'lernender_id' => $this->user->lernender->lernender_id, 'kategorie_id' => $kategorie['ABU'], 'semester_id' => $this->semester,
            'fach_id' => $fach, 'note_wert' => 4.0, 'pruefungsdatum' => '2026-04-01',
        ]);
    }

    private function zeugnis(string $zeile): Dokument
    {
        $inhalt = "BT\n/F1 12 Tf\n14 TL\n50 750 Td\n(".$zeile.") Tj T*\nET";
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

        $this->actingAs($this->user)->post(route('learner.documents.store'), [
            'datei' => new UploadedFile($tmp, 'zeugnis.pdf', 'application/pdf', null, true), 'art' => 'zeugnis', 'semester_id' => $this->semester,
        ])->assertSessionHasNoErrors();

        return Dokument::latest('dokument_id')->firstOrFail();
    }

    #[Test]
    public function abweichende_zeile_fuehrt_lernende_zu_den_noten_des_semesters(): void
    {
        $d = $this->zeugnis('Sprache und Kommunikation ........ 5,0');

        $this->get(route('learner.documents.reconcile', ['dokument_id' => $d->dokument_id, 'semester_id' => $this->semester]))->assertOk()
            ->assertSee('abweichend')->assertSee('Noten ansehen')
            ->assertSee(route('learner.grades.index', ['semester_id' => $this->semester]), false);
    }

    #[Test]
    public function abweichende_zeile_fuehrt_verwaltung_zu_den_noten_des_lernenden(): void
    {
        $d = $this->zeugnis('Sprache und Kommunikation ........ 5,0');
        $lernenderId = (int) $this->user->lernender->lernender_id;

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.learners.documents.reconcile', [$lernenderId, $d->dokument_id, 'semester_id' => $this->semester]))->assertOk()
            ->assertSee('Noten ansehen')
            ->assertSee(route('admin.learners.grades.index', ['lernender_id' => $lernenderId, 'semester_id' => $this->semester]), false);
    }

    #[Test]
    public function uebereinstimmende_zeile_hat_keinen_notenlink(): void
    {
        $d = $this->zeugnis('Sprache und Kommunikation ........ 4,0');

        $this->get(route('learner.documents.reconcile', ['dokument_id' => $d->dokument_id, 'semester_id' => $this->semester]))->assertOk()
            ->assertSee('stimmt')->assertDontSee('Noten ansehen');
    }
}
