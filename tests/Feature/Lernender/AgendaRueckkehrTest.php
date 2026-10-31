<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\CalendarFeed;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Planen, Bearbeiten und Entfernen führen in die Ansicht zurück, aus der sie kamen
 * (Monat bzw. Liste, Stundenplan-Schalter), ohne den Drawer erneut zu öffnen.
 */
class AgendaRueckkehrTest extends TestCase
{
    private User $user;

    private Modul $modul;

    protected function setUp(): void
    {
        parent::setUp();
        Semester::factory()->create();
        $this->user = User::factory()->lernender()->create();
        $this->modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id,
            'modul_id' => $this->modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id'),
        ]);
    }

    private function daten(string $datum, array $mehr = []): array
    {
        return ['bezug' => 'modul:'.$this->modul->modul_id, 'datum' => $datum, 'gewichtung_prozent' => 40, 'titel' => 'LB2', ...$mehr];
    }

    private function pruefung(string $datum): Pruefung
    {
        $this->actingAs($this->user)->post(route('learner.exams.store'), $this->daten($datum))->assertSessionHasNoErrors();

        return Pruefung::sole();
    }

    #[Test]
    public function planen_aus_dem_monat_zeigt_den_monat_des_pruefungsdatums_mit_stundenplan(): void
    {
        $datum = now()->addMonths(3)->startOfMonth()->addDays(9)->toDateString();

        $this->actingAs($this->user)
            ->post(route('learner.exams.store'), $this->daten($datum, ['ansicht' => 'monat', 'lektionen' => '1']))
            ->assertRedirect(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => substr($datum, 0, 7), 'lektionen' => 1]))
            ->assertSessionHas('success');
    }

    #[Test]
    public function bearbeiten_aus_dem_monat_bleibt_im_monat_und_folgt_dem_neuen_datum(): void
    {
        $p = $this->pruefung(now()->addMonth()->startOfMonth()->addDays(3)->toDateString());
        $neu = now()->addMonths(2)->startOfMonth()->addDays(5)->toDateString();

        $this->actingAs($this->user)
            ->put(route('learner.exams.update', $p->pruefung_id), $this->daten($neu, ['ansicht' => 'monat']))
            ->assertRedirect(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => substr($neu, 0, 7)]));
    }

    #[Test]
    public function ohne_kontext_bleibt_es_bei_der_nackten_liste(): void
    {
        $datum = now()->addWeek()->toDateString();

        $this->actingAs($this->user)->post(route('learner.exams.store'), $this->daten($datum))
            ->assertRedirect(route('learner.exams.index'));
    }

    #[Test]
    public function unerlaubte_werte_werden_nicht_durchgereicht(): void
    {
        $datum = now()->addWeek()->toDateString();

        $this->actingAs($this->user)
            ->post(route('learner.exams.store'), $this->daten($datum, ['ansicht' => 'http://evil.example', 'monat' => '9999-99', 'lektionen' => 'evil', 'planen' => '1']))
            ->assertRedirect(route('learner.exams.index'));
    }

    #[Test]
    public function monat_aus_dem_formular_wird_ignoriert_das_datum_zaehlt(): void
    {
        $datum = now()->addMonths(4)->startOfMonth()->addDays(2)->toDateString();

        $this->actingAs($this->user)
            ->post(route('learner.exams.store'), $this->daten($datum, ['ansicht' => 'monat', 'monat' => now()->format('Y-m')]))
            ->assertRedirect(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => substr($datum, 0, 7)]));
    }

    #[Test]
    public function entfernen_behaelt_den_stundenplan_schalter(): void
    {
        $p = $this->pruefung(now()->addWeek()->toDateString());

        $this->actingAs($this->user)
            ->delete(route('learner.exams.destroy', $p->pruefung_id), ['lektionen' => '1'])
            ->assertRedirect(route('learner.exams.index', ['lektionen' => 1]));
    }

    #[Test]
    public function drawer_formular_traegt_ansicht_und_stundenplan_aber_keinen_monat(): void
    {
        $monat = now()->addMonth()->format('Y-m');

        $html = $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $monat, 'lektionen' => 1, 'planen' => 1]))
            ->assertOk()->getContent();

        // Nur das Planen-Formular selbst: Schalter-Formular und Stundenplan-Knopf tragen dieselben Felder
        $this->assertSame(1, preg_match('#<form method="POST" action="'.preg_quote(route('learner.exams.store'), '#').'".*?</form>#s', $html, $form));
        $this->assertStringContainsString('<input type="hidden" name="ansicht" value="monat">', $form[0]);
        $this->assertStringContainsString('<input type="hidden" name="lektionen" value="1">', $form[0]);
        $this->assertStringNotContainsString('name="monat"', $form[0]);
    }

    #[Test]
    public function tages_drawer_verlinkt_bearbeiten_im_monat_mit_stundenplan(): void
    {
        $datum = now()->addMonth()->startOfMonth()->addDays(14)->toDateString();
        $p = $this->pruefung($datum);
        $monat = substr($datum, 0, 7);
        $erwartet = route('learner.exams.index', ['bearbeiten' => $p->pruefung_id, 'ansicht' => 'monat', 'monat' => $monat, 'lektionen' => 1]);

        $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $monat, 'lektionen' => 1]))
            ->assertOk()
            ->assertSee($this->alsJs($erwartet), false);
    }

    #[Test]
    public function bearbeiten_link_der_liste_behaelt_den_stundenplan(): void
    {
        $p = $this->pruefung(now()->addWeek()->toDateString());
        $erwartet = route('learner.exams.index', ['bearbeiten' => $p->pruefung_id, 'lektionen' => 1]);

        $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['lektionen' => 1]))
            ->assertOk()
            ->assertSee('href="'.e($erwartet).'"', false);
    }

    #[Test]
    public function entsperren_aus_dem_monat_bleibt_im_monat_der_pruefung(): void
    {
        $datum = now()->addMonths(2)->startOfMonth()->addDays(7)->toDateString();
        $p = $this->pruefung($datum);
        $feed = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://schulnetz.example/geheim']);
        $p->update(['quelle' => Pruefung::ICAL, 'extern_uid' => 'exam-x', 'calendar_feed_id' => $feed->id, 'lokal_gesperrt' => ['titel' => 'Alt']]);

        $html = $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['bearbeiten' => $p->pruefung_id, 'ansicht' => 'monat', 'monat' => substr($datum, 0, 7)]))
            ->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<form id="pruefung-entsperren"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="ansicht" value="monat">#', $html);

        $this->actingAs($this->user)
            ->post(route('learner.exams.unlock', $p->pruefung_id), ['ansicht' => 'monat'])
            ->assertRedirect(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => substr($datum, 0, 7)]))
            ->assertSessionHas('success');
        $this->assertNull($p->refresh()->lokal_gesperrt);
    }

    private function alsJs(string $wert): string
    {
        $js = (string) Js::from(['x' => $wert]);

        return substr($js, strlen("JSON.parse('{\\u0022x\\u0022:\\u0022"), -strlen("\\u0022}')"));
    }
}
