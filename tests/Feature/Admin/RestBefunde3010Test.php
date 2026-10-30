<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * Funktionsprüfung 30.10., Gruppe «rest»: Breite des Lernenden-Menüs, Terminname in der Agenda-Zelle,
 * Ladezustand je Knopf in der Kopie, Spinnerfarbe, Zahlenspalte Semester, defaultPrevented-Schutz in Formularen,
 * Löschabschnitt des Notenbaums.
 */
class RestBefunde3010Test extends TestCase
{
    use DatabaseTransactions;
    use VerwaltungTestHilfen;

    private string $ziel = '';

    protected function tearDown(): void
    {
        if ($this->ziel !== '') {
            File::deleteDirectory($this->ziel);
        }
        parent::tearDown();
    }

    /** Das Element mit dem gegebenen Kopf-Tag bis zum schliessenden Tag, für Klassenprüfungen. */
    private function tag(string $html, string $muster): string
    {
        $this->assertSame(1, preg_match($muster, $html, $treffer), 'Element nicht gefunden: '.$muster);

        return $treffer[0];
    }

    #[Test]
    public function lernenden_menue_hat_eine_feste_breite(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);

        $html = $this->actingAs($bb)->get(route('trainer.learners.grades.index', $lernender->lernender_id))->assertOk()->getContent();

        $menue = $this->tag($html, '~id="lernenden_wechseln".*?<div x-show="open" x-ref="panel"[^>]*>~s');
        $this->assertStringContainsString(' w-64 ', $menue);
        $this->assertDoesNotMatchRegularExpression('~ 64 ~', $menue);
    }

    #[Test]
    public function dropdown_kennt_die_breite_64(): void
    {
        $html = view('components.dropdown', ['width' => '64', 'trigger' => 'T', 'content' => 'C'])->render();

        $this->assertStringContainsString(' w-64 ', $html);
    }

    #[Test]
    public function agenda_monat_bricht_lange_terminnamen_um_statt_sie_abzuschneiden(): void
    {
        $user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create(['modul_nummer' => '903', 'titel' => 'Testmodul Gamma']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $user->lernender->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);
        $datum = now()->addWeek();
        $this->actingAs($user)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modul->modul_id, 'datum' => $datum->toDateString(), 'gewichtung_prozent' => 40,
            'titel' => 'Sicherheit umsetzen Schlusspruefung',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Pruefung::count());

        $html = $this->actingAs($user)->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $datum->format('Y-m')]))
            ->assertOk()->getContent();

        $span = $this->tag($html, '~<span class="[^"]*">[^<]*Sicherheit umsetzen Schlusspruefung[^<]*</span>~');
        $this->assertStringContainsString('line-clamp-2', $span);
        $this->assertStringNotContainsString('truncate', $span);
    }

    private function kopieEinrichten(User $admin): string
    {
        Storage::fake('local');
        $this->ziel = sys_get_temp_dir().'/np-kopie-'.uniqid();
        $this->actingAs($admin)->put(route('admin.operations.offsite.update'), [SicherungKopie::ZIEL => 'ordner', SicherungKopie::PFAD => $this->ziel])
            ->assertSessionHas('success');

        return $this->actingAs($admin)->get(route('admin.operations.edit'))->assertOk()->getContent();
    }

    #[Test]
    public function kopie_zeigt_den_spinner_nur_am_gedrueckten_knopf(): void
    {
        $html = $this->kopieEinrichten(User::factory()->admin()->create());

        $formular = $this->tag($html, '~<form[^>]*action="[^"]*operations/offsite#sicherung"[^>]*>.*?</form>~s');
        $this->assertStringContainsString(':aria-busy="loading"', $formular);
        $this->assertStringContainsString("x-show=\"loading && aktion === 'testen'\"", $formular);
        $this->assertStringContainsString("x-show=\"loading && aktion === 'kopieren'\"", $formular);
        $this->assertStringContainsString('$event.submitter?.value', $formular);
        // Der Wert des Knopfs geht nur mit, wenn er beim Absenden noch nicht gesperrt ist
        $this->assertStringContainsString('setTimeout(() => loading = true)', $formular);
    }

    #[Test]
    public function spinner_in_knoepfen_sind_neutral_und_nicht_akzentfarben(): void
    {
        $html = $this->kopieEinrichten(User::factory()->admin()->create());

        $this->assertStringNotContainsString('border-accent border-t-transparent', $html);
        // Kopie (2 Spinner), Sichern, Logo, Testmail
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'border-muted border-t-transparent'));
    }

    #[Test]
    public function spinner_der_betrieb_teilansichten_sind_neutral(): void
    {
        foreach (['_kopie', 'edit', '_logo', '_testmail'] as $datei) {
            $quelle = file_get_contents(resource_path("views/admin/betrieb/{$datei}.blade.php"));
            $this->assertStringNotContainsString('border-accent border-t-transparent', $quelle, $datei);
        }
    }

    private function lehrberufMitModul(): Lehrberuf
    {
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $modul = Modul::factory()->create();
        Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => (int) Kategorie::where('code', 'FACH')->value('kategorie_id'), 'pflicht' => 1, 'aktiv' => 1,
            'empfohlenes_lehrsemester_nr' => 3,
        ]);

        return $lehrberuf;
    }

    #[Test]
    public function semesterspalte_ist_rechtsbuendig(): void
    {
        $lehrberuf = $this->lehrberufMitModul();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.master-data.professions.show', $lehrberuf->lehrberuf_id))->assertOk()->getContent();

        $kopf = $this->tag($html, '~<th scope="col"[^>]*>Semester</th>~');
        $this->assertStringContainsString('text-right', $kopf);
        $this->assertStringNotContainsString('text-center', $kopf);

        $feld = $this->tag($html, '~<input type="number" name="empfohlenes_lehrsemester_nr"[^>]*>~');
        $this->assertStringContainsString('text-right tabular-nums', $feld);
        $this->assertStringNotContainsString('text-center', $feld);
        $this->assertStringNotContainsString('mx-auto', $feld);
    }

    #[Test]
    public function formulare_sperren_den_knopf_nur_wenn_das_absenden_nicht_abgebrochen_wurde(): void
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = $this->lehrberufMitModul();

        $seiten = [
            'Lehrberuf' => $this->actingAs($admin)->get(route('admin.master-data.professions.show', $lehrberuf->lehrberuf_id))->assertOk()->getContent(),
            'Notenbäume' => $this->actingAs($admin)->get(route('admin.master-data.grade-trees.index'))->assertOk()->getContent(),
            'Abbestellen' => $this->get(URL::signedRoute('notifications.unsubscribe', [
                'user' => User::factory()->lernender()->create()->benutzer_id, 'type' => NotificationCatalog::COMMENT_ADDED,
            ]))->assertOk()->getContent(),
        ];

        foreach ($seiten as $name => $html) {
            $this->assertStringNotContainsString('@submit="loading = true"', $html, $name);
            $this->assertStringContainsString('@submit="if (!$event.defaultPrevented) loading = true"', $html, $name);
        }
    }

    private function notenbaum(): int
    {
        $admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $this->actingAs($admin)->post(route('admin.master-data.grade-trees.template'), [
            'vorlage' => 'informatiker-efz-bivo2020', 'lehrberuf_id' => $lehrberuf->lehrberuf_id,
        ]);

        return (int) DB::table('notenbaeume')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->value('baum_id');
    }

    #[Test]
    public function notenbaum_in_gebrauch_zeigt_gesperrten_knopf_mit_hinweis(): void
    {
        $baum = $this->notenbaum();
        $knoten = (int) DB::table('notenbaum_knoten')->where('baum_id', $baum)->value('knoten_id');
        $lernender = User::factory()->lernender()->create();
        DB::table('notenbaum_positionen')->insert([
            'lernender_id' => $lernender->lernender->lernender_id, 'knoten_id' => $knoten, 'note_wert' => 5.0,
            'erfasst_von_benutzer_id' => $lernender->benutzer_id, 'erstellt_am' => now(), 'aktualisiert_am' => now(),
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.master-data.grade-trees.show', $baum))->assertOk()->getContent();

        $knopf = $this->tag($html, '~<button type="button" disabled aria-describedby="baum-loeschen-hinweis"[^>]*>~');
        $this->assertStringContainsString('np-knopf-gefahr', $knopf);
        $this->assertMatchesRegularExpression('~<p\s+id="baum-loeschen-hinweis"[^>]*>1 erfasste Noten gingen verloren\. Deaktiviere den Notenbaum stattdessen\.~', $html);
        $this->assertStringNotContainsString('<p class="text-sm text-muted">Zu diesem Notenbaum', $html);
        $this->assertStringNotContainsString('name="_method" value="DELETE"', $html);
    }

    #[Test]
    public function notenbaum_ohne_noten_loescht_ueber_gruppenzeile(): void
    {
        $baum = $this->notenbaum();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.master-data.grade-trees.show', $baum))->assertOk()->getContent();

        $this->assertStringContainsString('<section class="mt-10">', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('np-karte np-gruppe', $html);
        $this->assertStringNotContainsString('baum-loeschen-hinweis', $html);
    }
}
