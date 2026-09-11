<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Betreuung;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\Pruefung;
use App\Models\User;
use App\Services\Calendar\CalendarExport;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Sabre\VObject\Reader;
use Tests\TestCase;

class CalendarExportTest extends TestCase
{
    private User $lernender;

    private Pruefung $pruefung;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lernender = User::factory()->lernender()->create(['vorname' => 'Lea', 'nachname' => 'Muster']);
        $modul = Modul::factory()->create(['modul_nummer' => '159', 'titel' => 'Directory Services']);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->lernender->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::value('kategorie_id'),
        ]);
        $this->pruefung = Pruefung::create([
            'lernender_id' => $this->lernender->lernender->lernender_id, 'modul_id' => $modul->modul_id, 'titel' => 'LB1',
            'datum' => now()->addDays(5)->toDateString(), 'uhrzeit' => '13:15:00', 'dauer_minuten' => 45,
            'pruefungsart' => 'Onlineprüfung', 'stoff' => "- DNS\n- DHCP", 'gewichtung_prozent' => 33.33,
        ]);
    }

    #[Test]
    public function lernender_exportiert_eigene_pruefungen_mit_details(): void
    {
        $ics = app(CalendarExport::class)->forUser($this->lernender);
        $event = Reader::read($ics)->VEVENT;

        $this->assertStringContainsString('Prüfung 159 Directory Services – LB1', (string) $event->SUMMARY);
        $this->assertStringContainsString('Dauer: 45 Minuten', (string) $event->DESCRIPTION);
        $this->assertStringContainsString('- DHCP', (string) $event->DESCRIPTION);
        $this->assertSame('13:15', $event->DTSTART->getDateTime()->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('H:i'));
        $this->assertSame(45 * 60, $event->DTEND->getDateTime()->getTimestamp() - $event->DTSTART->getDateTime()->getTimestamp());
    }

    #[Test]
    public function berufsbildner_sieht_pruefungen_betreuter_mit_namen_fremde_nicht(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $fremderBb = User::factory()->berufsbildner()->create();
        Betreuung::create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $this->lernender->lernender->lernender_id,
            'gueltig_von' => now()->subYear()->toDateString(),
        ]);

        $this->assertStringContainsString('Lea Muster: Prüfung', app(CalendarExport::class)->forUser($bb));
        $this->assertStringNotContainsString('BEGIN:VEVENT', app(CalendarExport::class)->forUser($fremderBb));
    }

    #[Test]
    public function token_ist_stabil_bis_zum_zuruecksetzen(): void
    {
        $a = CalendarExport::token($this->lernender);
        $this->assertSame($a, CalendarExport::token($this->lernender->fresh()));
        $this->assertSame(48, strlen($a));
        $this->assertNotSame($a, CalendarExport::resetToken($this->lernender));
    }
}
