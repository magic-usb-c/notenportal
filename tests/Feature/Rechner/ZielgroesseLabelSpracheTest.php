<?php

declare(strict_types=1);

namespace Tests\Feature\Rechner;

use App\Models\Fach;
use App\Models\Semester;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Zielgroesse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Bezeichnungen von Zielen und Rechner folgen der Sprache der Oberfläche. */
class ZielgroesseLabelSpracheTest extends TestCase
{
    private function konfiguration(): array
    {
        $semester = Semester::factory()->create(['sortierung' => 2002]);
        $fach = Fach::factory()->create(['name' => 'Mathematik']);
        Konfiguration::vergessen();

        return [Konfiguration::ausDb(), (int) $semester->semester_id, (int) $fach->fach_id];
    }

    #[Test]
    public function bezeichnungen_sind_auf_englisch_uebersetzt(): void
    {
        [$k, $sid, $fid] = $this->konfiguration();
        app()->setLocale('en');

        $this->assertSame('Overall average', Zielgroesse::parse('gesamt')->label($k));
        $this->assertStringStartsWith('Semester average · ', Zielgroesse::parse("semester:{$sid}")->label($k));
        $this->assertSame('Mathematik · Apprenticeship', Zielgroesse::parse("fach:{$fid}")->label($k));
    }

    #[Test]
    public function bezeichnungen_bleiben_auf_deutsch(): void
    {
        [$k, $sid, $fid] = $this->konfiguration();
        app()->setLocale('de');

        $this->assertSame('Gesamtschnitt', Zielgroesse::parse('gesamt')->label($k));
        $this->assertStringStartsWith('Semesterschnitt · ', Zielgroesse::parse("semester:{$sid}")->label($k));
        $this->assertSame('Mathematik · Lehrzeit', Zielgroesse::parse("fach:{$fid}")->label($k));
    }
}
