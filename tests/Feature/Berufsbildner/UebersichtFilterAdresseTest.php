<?php

declare(strict_types=1);

namespace Tests\Feature\Berufsbildner;

use App\Models\Betreuung;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Kachelfilter und Suche der Berufsbildner-Übersicht liegen in der Adresse und überleben das Sortieren. */
class UebersichtFilterAdresseTest extends TestCase
{
    private User $bb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bb = User::factory()->berufsbildner()->create();
        foreach (['Aaron', 'Zumstein'] as $nachname) {
            $u = User::factory()->lernender()->create(['nachname' => $nachname]);
            Betreuung::factory()->create([
                'berufsbildner_id' => $this->bb->berufsbildner->berufsbildner_id,
                'lernender_id' => $u->lernender->lernender_id,
            ]);
        }
    }

    private function seite(array $query): string
    {
        // @js schreibt Zeichenketten in einfachen Anführungszeichen; zum Vergleichen vereinheitlichen
        return str_replace("'", '"', html_entity_decode($this->actingAs($this->bb)->get(route('trainer.dashboard', $query))->assertOk()->getContent()));
    }

    /** @return list<string> */
    private function sortLinks(string $html): array
    {
        $kopf = substr($html, (int) strpos($html, '<thead data-np-tabellenkopf'));
        $kopf = substr($kopf, 0, (int) strpos($kopf, '</thead>'));
        preg_match_all('/<a href="([^"]+)"/', $kopf, $treffer);

        return $treffer[1];
    }

    #[Test]
    public function liste_und_suche_aus_der_adresse_setzen_den_startzustand(): void
    {
        $html = $this->seite(['liste' => 'rot', 'suche' => 'zum']);

        $this->assertStringContainsString('filter: "rot"', $html);
        $this->assertStringContainsString('suche: "zum"', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="liste" value="rot"/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="suche" value="zum"/', $html);
    }

    #[Test]
    public function sortierlinks_tragen_liste_und_suche_mit(): void
    {
        $links = $this->sortLinks($this->seite(['liste' => 'neu', 'suche' => 'zum']));

        $this->assertNotEmpty($links);
        foreach ($links as $link) {
            $this->assertStringContainsString('liste=neu', $link);
            $this->assertStringContainsString('suche=zum', $link);
            $this->assertStringContainsString('order=', $link);
        }
    }

    #[Test]
    public function ungueltige_werte_fallen_auf_alle_und_leer_zurueck(): void
    {
        $html = $this->seite(['liste' => 'bogus', 'suche' => str_repeat('a', 300)]);

        $this->assertStringContainsString('filter: "alle"', $html);
        $this->assertStringContainsString('suche: "'.str_repeat('a', 100).'"', $html);
        // Die Rohadresse steht noch im Feedback-Dialog; massgeblich sind Startzustand und Sortierlinks
        $this->assertMatchesRegularExpression('/suche: "a{100}",/', $html);
        foreach ($this->sortLinks($html) as $link) {
            $this->assertStringNotContainsString(str_repeat('a', 101), $link);
        }
        foreach ($this->sortLinks($html) as $link) {
            $this->assertStringNotContainsString('liste=', $link);
        }

        $html = $this->seite(['liste' => ['rot'], 'suche' => ['x']]);
        $this->assertStringContainsString('filter: "alle"', $html);
        $this->assertStringContainsString('suche: ""', $html);
    }

    #[Test]
    public function ohne_filter_bleiben_die_sortierlinks_sauber(): void
    {
        $html = $this->seite([]);

        $this->assertStringContainsString('filter: "alle"', $html);
        foreach ($this->sortLinks($html) as $link) {
            $this->assertStringNotContainsString('liste=', $link);
            $this->assertStringNotContainsString('suche=', $link);
        }
    }
}
