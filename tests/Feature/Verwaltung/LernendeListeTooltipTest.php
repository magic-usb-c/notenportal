<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lernendenliste: gekürzte Zellen tragen den vollen Text als Tooltip. */
class LernendeListeTooltipTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function name_und_betreuer_tragen_den_vollen_text_als_tooltip(): void
    {
        $lernender = $this->neuerLernender(['email' => 'tooltip.lernend@example.test']);
        $bb = User::factory()->berufsbildner()->create();
        $this->betreue($bb, $lernender);
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('admin.learners.index'))->assertOk()->getContent();

        $name = e($lernender->benutzer->nachname.' '.$lernender->benutzer->vorname);
        $betreuer = e($bb->nachname.' '.$bb->vorname);

        $this->assertStringContainsString('title="'.$name.' · tooltip.lernend@example.test"', $html);
        $this->assertMatchesRegularExpression('/<td class="truncate text-muted" title="'.preg_quote($betreuer, '/').'">/', $html);
    }

    #[Test]
    public function ohne_betreuer_gibt_es_keinen_leeren_tooltip(): void
    {
        $this->neuerLernender();
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('admin.learners.index'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<td class="truncate text-muted" title="">/', $html);
        $this->assertMatchesRegularExpression('/<td class="truncate text-muted">\s*–/', $html);
    }
}
