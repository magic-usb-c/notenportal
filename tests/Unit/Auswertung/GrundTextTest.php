<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Notenbaum\Grund;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Der Text eines Grundes zeigt die Note so genau, dass Wert und Grenze nie gleich aussehen. */
class GrundTextTest extends TestCase
{
    #[Test]
    public function knapp_unter_der_grenze_bleibt_als_unterschied_lesbar(): void
    {
        $this->assertSame('IPA 3.95 liegt unter 4.0', (new Grund('IPA', 'IPA', Grund::FALLNOTE, 3.95, 4.0))->text());
        $this->assertSame('IPA 3.5 liegt unter 4.0', (new Grund('IPA', 'IPA', Grund::FALLNOTE, 3.5, 4.0))->text());
        $this->assertSame('IPA 3.99 liegt unter 4.0', (new Grund('IPA', 'IPA', Grund::FALLNOTE, 3.996, 4.0))->text());
        $this->assertSame('IPA 4.2 liegt unter 4.25', (new Grund('IPA', 'IPA', Grund::FALLNOTE, 4.2, 4.25))->text());
        $this->assertSame('QV: 2.5 Minuspunkte, erlaubt sind 2.0', (new Grund('QV', 'QV', Grund::MINUSPUNKTE, 2.5, 2.0))->text());
    }
}
