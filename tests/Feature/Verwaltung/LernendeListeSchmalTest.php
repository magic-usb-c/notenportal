<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lernendenliste in schmalen Fenstern: Die Tabelle scrollt in ihrer Karte, statt Marken zu überlappen oder die Seite zu verbreitern. */
class LernendeListeSchmalTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function tabelle_hat_untergrenze_und_eigenen_scrollbereich(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($rolle, $lernender);

        $html = $this->actingAs($verwalter)->get(route("{$rolle}.learners.index"))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="overflow-x-auto rounded-xl p-2">\s*<table class="np-tabelle table-fixed text-sm min-w-\[\d+rem\]">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div class="np-karte[^"]*overflow-hidden" data-np-liste>/', $html);
    }
}
