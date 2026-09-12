<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Support\Format;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * app/Support/Format.php::restdauer(): Restdauer mit automatischer Einheit
 * (Tage bis 6 Tage, Wochen ab 7 Tagen, Monate ab 30 Tagen), Grundlage von Block B.
 *
 * Die Grenzen liegen auf der vollen Einheit (7/30 Tage), damit die Einzahl «1 Woche»/
 * «1 Monat» tatsächlich vorkommt (Review-Nachbesserung: bei den alten Grenzen >14/>60
 * war round(tage/7) bzw. round(tage/30) an der Grenze immer schon >= 2).
 */
class RestdauerTest extends TestCase
{
    #[Test]
    public function heute_oder_vorbei_zeigt_endet_heute(): void
    {
        $this->assertSame('endet heute', Format::restdauer(Carbon::today()));
        $this->assertSame('endet heute', Format::restdauer(Carbon::yesterday()));
    }

    #[Test]
    public function ein_tag_ist_singular(): void
    {
        $this->assertSame('noch 1 Tag', Format::restdauer(Carbon::today()->addDay()));
    }

    #[Test]
    public function mehrere_tage_bis_zur_wochengrenze(): void
    {
        $this->assertSame('noch 5 Tage', Format::restdauer(Carbon::today()->addDays(5)));
        // 6 Tage sind noch "Tage", nicht "Wochen" (Grenze ist >= 7).
        $this->assertSame('noch 6 Tage', Format::restdauer(Carbon::today()->addDays(6)));
    }

    #[Test]
    public function ab_7_tagen_eine_woche_singular(): void
    {
        // 7 Tage runden exakt auf 1 Woche (round(7/7) = 1) - die Einzahl ist damit erreichbar.
        $this->assertSame('noch 1 Woche', Format::restdauer(Carbon::today()->addDays(7)));
        $this->assertSame('noch 1 Woche', Format::restdauer(Carbon::today()->addDays(10)));
    }

    #[Test]
    public function mehrere_wochen_bis_zur_monatsgrenze(): void
    {
        $this->assertSame('noch 2 Wochen', Format::restdauer(Carbon::today()->addDays(15)));
        // 29 Tage sind noch "Wochen", nicht "Monate" (Grenze ist >= 30).
        $this->assertSame('noch 4 Wochen', Format::restdauer(Carbon::today()->addDays(29)));
    }

    #[Test]
    public function ab_30_tagen_ein_monat_singular(): void
    {
        // 30 Tage runden exakt auf 1 Monat (round(30/30) = 1) - die Einzahl ist damit erreichbar.
        $this->assertSame('noch 1 Monat', Format::restdauer(Carbon::today()->addDays(30)));
        $this->assertSame('noch 1 Monat', Format::restdauer(Carbon::today()->addDays(44)));
    }

    #[Test]
    public function mehrere_monate(): void
    {
        $this->assertSame('noch 2 Monate', Format::restdauer(Carbon::today()->addDays(61)));
        $this->assertSame('noch 6 Monate', Format::restdauer(Carbon::today()->addDays(180)));
    }
}
