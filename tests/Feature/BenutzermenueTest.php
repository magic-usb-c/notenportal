<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Schlankes Benutzermenü (Rückmeldung #4 des Product Owners): je Rolle genau 3 Einträge -
 * Einstellungen, Feedback, Abmelden - im Kontomenü der Symbolleiste (#np-benutzermenue). Ein eigenes
 * Mobilmenü gibt es nicht mehr: unter 1024 px öffnet die Seitenleiste als Schublade, das Kontomenü
 * bleibt dasselbe. Der Darstellungs-Umschalter darüber gehört nicht zum Benutzermenü und zählt nicht mit.
 */
class BenutzermenueTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function rollen(): array
    {
        return [
            'Lernender' => ['lernender'],
            'Berufsbildner' => ['berufsbildner'],
            'Admin' => ['admin'],
        ];
    }

    #[Test]
    #[DataProvider('rollen')]
    public function menue_hat_je_rolle_genau_drei_eintraege(string $rolleState): void
    {
        $benutzer = User::factory()->{$rolleState}()->create();
        // route('dashboard') leitet auf das Rollen-Dashboard um (302); Inhalt erst nach dem Redirect.
        $html = $this->actingAs($benutzer)->followingRedirects()->get(route('dashboard'))->getContent();

        $this->assertStringNotContainsString('id="np-benutzermenue-mobil"', $html);

        foreach (['np-benutzermenue'] as $containerId) {
            $container = $this->container($html, $containerId);

            $this->assertStringContainsString(__('Einstellungen'), $container, "Container #{$containerId} ohne «Einstellungen».");
            $this->assertStringContainsString(__('Feedback'), $container, "Container #{$containerId} ohne «Feedback».");
            $this->assertStringContainsString(__('Abmelden'), $container, "Container #{$containerId} ohne «Abmelden».");

            // Alte Einträge (Profil, Benachrichtigungen, Tastenkürzel, Meine Meldungen, Meine Daten
            // herunterladen, Sprache) dürfen nicht mehr im Benutzermenü stehen.
            $anzahl = substr_count($container, '<a ') + substr_count($container, '<button');
            $this->assertSame(3, $anzahl, "Container #{$containerId}: erwartet 3 Einträge, gefunden {$anzahl}.\n{$container}");
        }
    }

    /**
     * Inhalt eines <div id="…">…</div> anhand seiner id herausschneiden (Tiefenzählung, falls
     * darin nochmals <div> vorkämen - aktuell nicht der Fall, aber robust gegen künftige Änderungen).
     */
    private function container(string $html, string $id): string
    {
        $idPos = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($idPos, "Container #{$id} nicht im HTML gefunden.");

        $divStart = strrpos(substr($html, 0, $idPos), '<div');
        $this->assertNotFalse($divStart, "Öffnendes <div> für #{$id} nicht gefunden.");

        $rest = substr($html, $divStart);
        $tiefe = 0;
        $pos = 0;
        while (preg_match('/<div\b|<\/div>/', $rest, $treffer, PREG_OFFSET_CAPTURE, $pos)) {
            [$tag, $tagPos] = $treffer[0];
            $pos = $tagPos + strlen($tag);
            $tiefe += $tag === '</div>' ? -1 : 1;
            if ($tiefe === 0) {
                return substr($rest, 0, $pos);
            }
        }

        $this->fail("Schliessendes </div> für #{$id} nicht gefunden.");
    }
}
