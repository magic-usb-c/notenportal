<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lernendenliste: Vollnamen-Suche, künftig datierte Noten; Lernende bearbeiten: Lehrzeit gegen vorhandene Noten. */
class LernendeListeBefundeTest extends TestCase
{
    use VerwaltungTestHilfen;

    /** @return list<string> Nachnamen der gelisteten Lernenden */
    private function gefunden(User $verwalter, string $rolle, string $suche): array
    {
        return $this->actingAs($verwalter)
            ->get(route("{$rolle}.learners.index", ['suche' => $suche]))
            ->assertOk()
            ->viewData('zeilen')
            ->map(fn ($z) => $z->nachname)
            ->all();
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function suche_findet_den_vollen_namen_in_beiden_reihenfolgen(string $rolle): void
    {
        $lernender = $this->neuerLernender(['vorname' => 'Mirella', 'nachname' => 'Zumbrunnen']);
        $verwalter = $this->verwalter($rolle, $lernender);
        $andere = $this->neuerLernender(['vorname' => 'Mirella', 'nachname' => 'Anderegg']);
        if ($rolle === 'trainer') {
            $this->betreue($verwalter, $andere);
        }

        $this->assertSame(['Zumbrunnen'], $this->gefunden($verwalter, $rolle, 'Mirella Zumbrunnen'));
        $this->assertSame(['Zumbrunnen'], $this->gefunden($verwalter, $rolle, 'Zumbrunnen Mirella'));
        $this->assertSame(['Zumbrunnen'], $this->gefunden($verwalter, $rolle, 'rella Zumbr'));
        $this->assertSame(['Zumbrunnen'], $this->gefunden($verwalter, $rolle, 'Zumbrunnen'));
    }

    #[Test]
    public function suche_mit_platzhalterzeichen_trifft_nicht_alles(): void
    {
        $this->neuerLernender(['vorname' => 'Mirella', 'nachname' => 'Zumbrunnen']);
        $admin = User::factory()->admin()->create();

        $this->assertSame([], $this->gefunden($admin, 'admin', 'Mirella%Zumbrunnen'));
        $this->assertSame([], $this->gefunden($admin, 'admin', 'Mirella_Zumbrunnen'));
    }

    #[Test]
    public function zukuenftige_note_zaehlt_nicht_als_letzte_note(): void
    {
        $lernender = $this->neuerLernender(['nachname' => 'Zukunftsmann']);
        $bb = $this->verwalter('trainer', $lernender);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => now()->addDays(10)->toDateString()]);

        $html = (string) $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/vor -\d+ Tagen/', $html);

        $zeile = $this->actingAs($bb)->get(route('trainer.learners.index'))->viewData('zeilen')->first();
        $this->assertNull($zeile->lastNote);
        $this->assertSame(1, $zeile->anzahl, 'Die Anzahl zählt weiterhin alle Noten');

        $gefiltert = $this->actingAs($bb)->get(route('trainer.learners.index', ['warnung' => 'keine_noten']))->assertOk()->viewData('zeilen');
        $this->assertSame(['Zukunftsmann'], $gefiltert->map(fn ($z) => $z->nachname)->all());
    }

    #[Test]
    public function letzte_note_ist_die_juengste_vergangene_note(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);
        $vergangen = now()->subDays(3)->toDateString();
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => $vergangen]);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => now()->addDays(20)->toDateString()]);

        $zeile = $this->actingAs($bb)->get(route('trainer.learners.index'))->assertOk()->viewData('zeilen')->first();

        $this->assertSame($vergangen, substr((string) $zeile->lastNote, 0, 10));
    }

    private function lehrzeit(array $ueberschreibung): array
    {
        return array_merge([
            'vorname' => 'Mia',
            'nachname' => 'Muster',
            'email' => 'mia.lehrzeit@example.test',
            'benutzername' => 'mialehrzeit',
            'lehrbeginn' => '2023-08-01',
            'lehrende' => '2027-07-31',
        ], $ueberschreibung);
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function lehrbeginn_nach_vorhandener_note_wird_abgelehnt(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($rolle, $lernender);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => '2023-08-26']);

        $this->actingAs($verwalter)
            ->put(route("{$rolle}.learners.update", $lernender->lernender_id), $this->lehrzeit(['lehrberuf_id' => $lernender->lehrberuf_id, 'lehrbeginn' => '2023-09-01']))
            ->assertSessionHasErrors(['lehrbeginn']);

        $this->assertSame('26.08.2023', $this->datumAusMeldung(session('errors')->first('lehrbeginn')));
        $this->assertNotSame('2023-09-01', $lernender->fresh()->lehrbeginn->toDateString(), 'Nichts gespeichert');
    }

    #[Test]
    public function lehrende_vor_vorhandener_note_wird_abgelehnt(): void
    {
        $lernender = $this->neuerLernender();
        $admin = User::factory()->admin()->create();
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => '2024-03-15']);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => '2024-11-20']);

        $this->actingAs($admin)
            ->put(route('admin.learners.update', $lernender->lernender_id), $this->lehrzeit(['lehrberuf_id' => $lernender->lehrberuf_id, 'lehrende' => '2024-06-30']))
            ->assertSessionHasErrors(['lehrende']);

        $this->assertSame('20.11.2024', $this->datumAusMeldung(session('errors')->first('lehrende')));
    }

    #[Test]
    public function geloeschte_noten_und_randtage_sperren_die_lehrzeit_nicht(): void
    {
        $lernender = $this->neuerLernender();
        $admin = User::factory()->admin()->create();
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => '2023-08-01']);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => '2027-07-31']);
        Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => '2022-01-10', 'geloescht_am' => now()]);

        $this->actingAs($admin)
            ->put(route('admin.learners.update', $lernender->lernender_id), $this->lehrzeit(['lehrberuf_id' => $lernender->lehrberuf_id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('2023-08-01', $lernender->fresh()->lehrbeginn->toDateString());
    }

    private function datumAusMeldung(string $meldung): string
    {
        $this->assertMatchesRegularExpression('/\d{2}\.\d{2}\.\d{4}/', $meldung);
        preg_match('/\d{2}\.\d{2}\.\d{4}/', $meldung, $treffer);

        return $treffer[0];
    }
}
