<?php

declare(strict_types=1);

namespace Tests\Unit\Auswertung;

use App\Services\Auswertung\Leistung;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** app/Services/Auswertung/Leistung.php: unveränderliches Wertobjekt einer Prüfung im Rechenmodell. */
class LeistungTest extends TestCase
{
    #[Test]
    public function mit_wert_ersetzt_nur_den_wert(): void
    {
        $leistung = new Leistung(kategorieId: 1, fachId: 2, modulId: null, semesterId: 3, datum: '2026-01-01', wert: null, gewicht: 50.0, quelle: Leistung::GEPLANT, id: 9, titel: 'Prüfung');

        $mitWert = $leistung->mitWert(4.5);

        $this->assertTrue($leistung->istUnbekannt());
        $this->assertFalse($mitWert->istUnbekannt());
        $this->assertSame(4.5, $mitWert->wert);
        // alle übrigen Felder bleiben unverändert
        $this->assertSame($leistung->kategorieId, $mitWert->kategorieId);
        $this->assertSame($leistung->fachId, $mitWert->fachId);
        $this->assertSame($leistung->semesterId, $mitWert->semesterId);
        $this->assertSame($leistung->datum, $mitWert->datum);
        $this->assertSame($leistung->gewicht, $mitWert->gewicht);
        $this->assertSame($leistung->quelle, $mitWert->quelle);
        $this->assertSame($leistung->id, $mitWert->id);
        $this->assertSame($leistung->titel, $mitWert->titel);
    }

    #[Test]
    public function ist_unbekannt_prueft_nur_den_wert(): void
    {
        $ohneWert = new Leistung(1, null, 1, null, null, null);
        $mitWert = new Leistung(1, null, 1, null, null, 5.0);

        $this->assertTrue($ohneWert->istUnbekannt());
        $this->assertFalse($mitWert->istUnbekannt());
    }

    #[Test]
    public function schluessel_fuer_ein_fach_mit_semester(): void
    {
        $leistung = new Leistung(kategorieId: 1, fachId: 7, modulId: null, semesterId: 2, datum: null, wert: null);

        $this->assertSame('f7s2', $leistung->schluessel(2));
    }

    #[Test]
    public function schluessel_fuer_ein_fach_ohne_semester_ist_unbestimmt(): void
    {
        $leistung = new Leistung(kategorieId: 1, fachId: 7, modulId: null, semesterId: null, datum: null, wert: null);

        $this->assertNull($leistung->schluessel(null));
    }

    #[Test]
    public function schluessel_fuer_ein_modul_braucht_kein_semester(): void
    {
        $leistung = new Leistung(kategorieId: 1, fachId: null, modulId: 42, semesterId: null, datum: null, wert: null);

        $this->assertSame('m42', $leistung->schluessel(null));
        $this->assertSame('m42', $leistung->schluessel(5), 'Modul-Leistungen hängen nicht vom übergebenen Semester ab');
    }

    #[Test]
    public function to_array_liefert_die_darstellbaren_felder(): void
    {
        $leistung = new Leistung(kategorieId: 1, fachId: 2, modulId: null, semesterId: 3, datum: '2026-03-15', wert: 5.25, gewicht: 75.0, quelle: Leistung::NOTE, id: 11, titel: 'LB2');

        $this->assertSame([
            'id' => 11,
            'quelle' => Leistung::NOTE,
            'datum' => '2026-03-15',
            'titel' => 'LB2',
            'wert' => 5.25,
            'gewicht' => 75.0,
        ], $leistung->toArray());
    }
}
