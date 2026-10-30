<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\User;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tages-Drawer der Agenda: «Prüfung planen» führt mit dem gewählten Datum in den Prüfungs-Drawer
 * (Link statt zweitem Drawer über dem ersten); das Formular übernimmt nur ein gültiges Datum.
 */
class AgendaTagPlanenTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
    }

    /** Innerer Text des Wertes, wie ihn `@js(...)` im Markup ausgibt (JSON in JSON.parse('…')). */
    private function alsJs(string $wert): string
    {
        // Als Feld eines Arrays, denn nur Arrays gehen als JSON.parse('…') (mit verdoppelten Backslashes) ins Markup
        $js = (string) Js::from(['x' => $wert]);

        return substr($js, strlen("JSON.parse('{\\u0022x\\u0022:\\u0022"), -strlen("\\u0022}')"));
    }

    #[Test]
    public function planen_mit_datum_belegt_das_datumsfeld_vor(): void
    {
        $datum = now()->addMonths(2)->startOfMonth()->addDays(9)->toDateString();

        $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['planen' => 1, 'datum' => $datum]))
            ->assertOk()
            ->assertSee('id="datum" name="datum" required value="'.$datum.'"', false);
    }

    #[Test]
    public function ungueltiges_datum_in_der_adresse_wird_nicht_uebernommen(): void
    {
        foreach (['2026-02-30', 'morgen', '2026-1-5', '"><script>x</script>', '2026-05-05 12:00'] as $falsch) {
            $this->actingAs($this->user)
                ->get(route('learner.exams.index', ['planen' => 1, 'datum' => $falsch]))
                ->assertOk()
                ->assertSee('id="datum" name="datum" required value=""', false);
        }
    }

    #[Test]
    public function tages_drawer_bietet_je_tag_pruefung_planen_mit_datum_und_bleibt_im_monat(): void
    {
        $monat = now()->addMonth()->format('Y-m');
        $tag = $monat.'-15';
        $erwartet = route('learner.exams.index', ['planen' => 1, 'datum' => $tag, 'ansicht' => 'monat', 'monat' => $monat]);

        $this->actingAs($this->user)
            ->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $monat]))
            ->assertOk()
            ->assertSee($this->alsJs($erwartet), false)
            ->assertSee(':href="tagAusgewaehlt.planen"', false);
    }

    #[Test]
    public function tages_drawer_der_listenansicht_verlaesst_die_liste_nicht(): void
    {
        $tag = now()->format('Y-m-d');
        $erwartet = route('learner.exams.index', ['planen' => 1, 'datum' => $tag]);

        $this->actingAs($this->user)
            ->get(route('learner.exams.index'))
            ->assertOk()
            ->assertSee($this->alsJs($erwartet), false);
    }
}
