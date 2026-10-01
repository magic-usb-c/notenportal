<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Cockpit-Navigation «Übersicht · Noten · Dokumente · Rechner · Profil & Betreuung» steht in der Verwaltung auf
 * dem Cockpit und auf den drei Unterseiten; der aktuelle Eintrag trägt aria-current="page". Lernende sehen sie nicht.
 */
class CockpitTabsTest extends TestCase
{
    use VerwaltungTestHilfen;

    private const EINTRAEGE = ['Übersicht', 'Noten', 'Dokumente', 'Rechner', 'Profil & Betreuung'];

    /** @return array<string, array{0: string, 1: string, 2: string}> Rolle, Routenname (ohne Bereich), aktiver Eintrag */
    public static function seiten(): array
    {
        $faelle = [];
        foreach (['admin', 'trainer'] as $rolle) {
            foreach ([
                'learners.show' => 'Übersicht',
                'learners.grades.index' => 'Noten',
                'learners.documents.index' => 'Dokumente',
                'learners.calculator' => 'Rechner',
            ] as $route => $aktiv) {
                $faelle["{$rolle} {$route}"] = [$rolle, $route, $aktiv];
            }
        }

        return $faelle;
    }

    /** @return array<string, bool> Beschriftung => trägt aria-current="page" (in Reihenfolge der Leiste) */
    private function leiste(string $html): array
    {
        $this->assertSame(1, preg_match('#<nav aria-label="Bereiche" class="np-segment[^"]*">(.*?)</nav>#s', $html, $nav), 'Cockpit-Navigation fehlt');
        preg_match_all('#<(a|button)\b([^>]*)>([^<]*)</\1>#s', $nav[1], $teile, PREG_SET_ORDER);

        $leiste = [];
        foreach ($teile as $teil) {
            $leiste[html_entity_decode(trim($teil[3]))] = (bool) preg_match('/(?<![:\w-])aria-current="page"/', $teil[2]);
        }

        return $leiste;
    }

    #[Test]
    #[DataProvider('seiten')]
    public function leiste_steht_auf_cockpit_und_unterseiten(string $rolle, string $route, string $aktiv): void
    {
        $lernender = $this->neuerLernender();
        $user = $this->verwalter($rolle, $lernender);

        $html = (string) $this->actingAs($user)->get(route("{$rolle}.{$route}", $lernender->lernender_id))->assertOk()->getContent();
        $leiste = $this->leiste($html);

        $this->assertSame(self::EINTRAEGE, array_keys($leiste));
        $this->assertSame([$aktiv], array_keys(array_filter($leiste)), 'Genau der Eintrag der Seite ist aktiv');
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function profil_ist_aktiv_und_unterseiten_verlinken_auf_cockpit_und_profil(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        $user = $this->verwalter($rolle, $lernender);
        $id = $lernender->lernender_id;

        $profil = $this->leiste((string) $this->actingAs($user)->get(route("{$rolle}.learners.show", [$id, 'tab' => 'profil']))->assertOk()->getContent());
        $this->assertSame(['Profil & Betreuung'], array_keys(array_filter($profil)));

        $this->actingAs($user)->get(route("{$rolle}.learners.grades.index", $id))->assertOk()
            ->assertSee('href="'.route("{$rolle}.learners.show", $id).'"', false)
            ->assertSee('href="'.route("{$rolle}.learners.show", [$id, 'tab' => 'profil']).'"', false);
    }

    #[Test]
    public function lernende_sehen_die_leiste_nicht(): void
    {
        $user = User::factory()->lernender()->create();

        foreach (['learner.grades.index', 'learner.grades.calculator', 'learner.documents.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertDontSee('Profil &amp; Betreuung', false)
                ->assertDontSee('aria-label="Bereiche"', false);
        }
    }
}
