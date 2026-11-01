<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Tagesraster der Agenda sind je ein Tab-Stopp (roving tabindex), die Pfeiltasten bewegen den Fokus.
 */
class AgendaRasterTastaturTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Semester::factory()->create();
        $this->user = User::factory()->lernender()->create();
    }

    /** @return list<string> die öffnenden Tags aller Tagesschaltflächen des Rasters */
    private function tageKnoepfe(string $html, string $raster): array
    {
        // je Seite gibt es nur ein Raster, Tagesschaltflächen stehen nur darin
        $this->assertStringContainsString('data-np-raster="'.$raster.'"', $html, "Raster {$raster} fehlt");
        preg_match_all('/<button\b[^>]*data-np-tag="[^"]+"[^>]*>/', $html, $knoepfe);

        return $knoepfe[0];
    }

    private function mitTabStopp(array $knoepfe): array
    {
        return array_values(array_filter($knoepfe, fn (string $k) => str_contains($k, 'tabindex="0"')));
    }

    #[Test]
    public function das_kleine_raster_hat_genau_einen_tab_stopp_auf_heute(): void
    {
        $html = $this->actingAs($this->user)->get(route('learner.exams.index'))->assertOk()->getContent();

        $knoepfe = $this->tageKnoepfe($html, 'mini');
        $this->assertGreaterThanOrEqual(28, count($knoepfe));
        $stopps = $this->mitTabStopp($knoepfe);
        $this->assertCount(1, $stopps);
        $this->assertStringContainsString('data-np-tag="'.now()->toDateString().'"', $stopps[0]);
        $this->assertSame(count($knoepfe) - 1, substr_count(implode('', $knoepfe), 'tabindex="-1"'));
    }

    #[Test]
    public function das_grosse_raster_hat_genau_einen_tab_stopp(): void
    {
        $html = $this->actingAs($this->user)->get(route('learner.exams.index', ['ansicht' => 'monat']))->assertOk()->getContent();

        $knoepfe = $this->tageKnoepfe($html, 'monat');
        $this->assertGreaterThanOrEqual(35, count($knoepfe));
        $stopps = $this->mitTabStopp($knoepfe);
        $this->assertCount(1, $stopps);
        $this->assertStringContainsString('data-np-tag="'.now()->toDateString().'"', $stopps[0]);
    }

    #[Test]
    public function in_einem_anderen_monat_liegt_der_tab_stopp_auf_dem_ersten_tag_des_monats(): void
    {
        $monat = now()->addMonths(2)->startOfMonth();
        $html = $this->actingAs($this->user)->get(route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $monat->format('Y-m')]))->assertOk()->getContent();

        $stopps = $this->mitTabStopp($this->tageKnoepfe($html, 'monat'));
        $this->assertCount(1, $stopps);
        $this->assertStringContainsString('data-np-tag="'.$monat->toDateString().'"', $stopps[0]);
    }

    #[Test]
    public function beide_raster_nehmen_die_pfeiltasten_entgegen(): void
    {
        $liste = $this->actingAs($this->user)->get(route('learner.exams.index'))->assertOk()->getContent();
        $monat = $this->actingAs($this->user)->get(route('learner.exams.index', ['ansicht' => 'monat']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-np-raster="mini"[^>]*@keydown="tagTaste\(\$event\)"/', $liste);
        $this->assertMatchesRegularExpression('/data-np-raster="monat"[^>]*@keydown="tagTaste\(\$event\)"/', $monat);
    }
}
