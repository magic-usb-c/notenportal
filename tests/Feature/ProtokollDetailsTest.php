<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Protokoll;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Details im Aktivitätsprotokoll erscheinen als lesbarer Text, nicht als JSON. */
class ProtokollDetailsTest extends TestCase
{
    #[Test]
    public function details_werden_mit_bezeichnungen_und_trennpunkten_geschrieben(): void
    {
        $this->assertSame('Rolle: Berufsbildner · Aktiv: Nein', Protokoll::detailText(['rolle' => 'Berufsbildner', 'aktiv' => false]));
        $this->assertSame('Vorher: Admin · Nachher: Admin, Berufsbildner', Protokoll::detailText(['alt' => ['Admin'], 'neu' => ['Admin', 'Berufsbildner']]));
        $this->assertSame('Positionen: (Position: IPA · Vorher: 4.5 · Nachher: 5)',
            Protokoll::detailText(['positionen' => [['position' => 'IPA', 'vorher' => 4.5, 'nachher' => 5]]]));
        $this->assertSame('Einstellung neu: –', Protokoll::detailText(['einstellung_neu' => null]));
        $this->assertStringContainsString('KB', Protokoll::detailText(['bytes' => 2048]));
        $this->assertSame('', Protokoll::detailText(null));
    }
}
