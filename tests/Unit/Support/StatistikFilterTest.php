<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\StatistikFilter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatistikFilterTest extends TestCase
{
    private const array REGELN = ['zeitraum' => ['semester', 'lehrjahr', 'alles'], 'kategorie' => ['id' => [1, 2, 3]], 'wochen' => [12, 26, 52]];

    private const array STANDARD = ['zeitraum' => 'semester', 'kategorie' => null, 'wochen' => 12];

    private function filter(array $query): StatistikFilter
    {
        return StatistikFilter::aus(Request::create('/x', 'GET', $query), self::REGELN, self::STANDARD);
    }

    #[Test]
    public function erlaubte_werte_werden_uebernommen(): void
    {
        $f = $this->filter(['zeitraum' => 'alles', 'kategorie' => '2', 'wochen' => '26']);

        $this->assertSame('alles', $f->wert('zeitraum'));
        $this->assertSame(2, $f->wert('kategorie'), 'IDs kommen als int zurück');
        $this->assertSame(26, $f->wert('wochen'), 'Listenwerte behalten ihren Typ aus der Regel');
    }

    #[Test]
    public function unbekannte_werte_und_schluessel_fallen_auf_den_standard(): void
    {
        $f = $this->filter(['zeitraum' => 'xyz', 'kategorie' => '99', 'wochen' => '13', 'fremd' => 'x']);

        $this->assertSame('semester', $f->wert('zeitraum'));
        $this->assertNull($f->wert('kategorie'));
        $this->assertSame(12, $f->wert('wochen'));
        $this->assertNull($f->wert('fremd'));
        $this->assertArrayNotHasKey('fremd', $f->toArray());
        $this->assertSame([], $f->query());
    }

    #[Test]
    public function ungueltige_formen_sind_kein_fehler(): void
    {
        $f = $this->filter(['zeitraum' => ['alles'], 'kategorie' => '2; DROP', 'wochen' => '']);

        $this->assertSame('semester', $f->wert('zeitraum'));
        $this->assertNull($f->wert('kategorie'));
        $this->assertSame(12, $f->wert('wochen'));
        $this->assertNull($this->filter(['kategorie' => '02'])->wert('kategorie'), 'IDs ohne führende Null');
        $this->assertNull($this->filter(['kategorie' => '1.0'])->wert('kategorie'));
    }

    #[Test]
    public function query_enthaelt_nur_was_vom_standard_abweicht(): void
    {
        $f = $this->filter(['zeitraum' => 'semester', 'kategorie' => '3', 'wochen' => '52']);

        $this->assertSame(['kategorie' => 3, 'wochen' => 52], $f->query());
        $this->assertSame(['zeitraum' => 'semester', 'kategorie' => 3, 'wochen' => 52], $f->toArray());
    }

    #[Test]
    public function ohne_standard_ist_der_wert_null(): void
    {
        $f = StatistikFilter::aus(Request::create('/x', 'GET', ['zeitraum' => 'nein']), ['zeitraum' => ['a', 'b']], []);

        $this->assertNull($f->wert('zeitraum'));
        $this->assertSame([], $f->query());
    }
}
