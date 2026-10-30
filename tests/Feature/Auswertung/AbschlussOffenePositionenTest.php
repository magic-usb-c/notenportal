<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Services\Auswertung\Zielrechner;
use App\Support\StatistikDaten;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Verwaltung\VerwaltungTestHilfen;
use Tests\TestCase;

/**
 * «Was fehlt zum Bestehen» (S5): offene Positionen von Hand erscheinen als unbekannte Leistung
 * (BaumLader liefert nur erfasste), und die Zeilen der Agenda tragen die Prüfungs-ID.
 */
class AbschlussOffenePositionenTest extends TestCase
{
    use VerwaltungTestHilfen;

    private User $user;

    private int $lernenderId;

    protected function setUp(): void
    {
        parent::setUp();

        $lehrberuf = DB::table('lehrberufe')->insertGetId(['kuerzel' => 'OPT', 'name' => 'Offene-Positionen-Test EFZ']);
        DB::table('semester')->insert(['bezeichnung' => 'OP-1', 'start_datum' => '2024-08-01', 'end_datum' => now()->addYears(3)->toDateString(), 'sortierung' => 1]);
        Konfiguration::vergessen();
        $lernender = $this->neuerLernender([], ['lehrberuf_id' => $lehrberuf]);
        $this->user = $lernender->benutzer;
        $this->lernenderId = (int) $lernender->lernender_id;
        app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $lehrberuf);
    }

    private function knoten(string $code): int
    {
        return (int) DB::table('notenbaum_knoten')->where('code', $code)->value('knoten_id');
    }

    /** @return list<array<string, mixed>> */
    private function zeilen(): array
    {
        return $this->actingAs($this->user)->getJson(route('learner.qualification.index'))
            ->assertOk()->json('diagramm.positionen.zeilen') ?? [];
    }

    #[Test]
    public function abschluss_zeigt_fehlende_positionen_mit_noetiger_note_samt_zusammenfassung_und_details(): void
    {
        $zeilen = $this->zeilen();

        $this->assertCount(3, $zeilen, 'IPA, Schlussarbeit und Schlussprüfung sind offen');
        $ipa = collect($zeilen)->firstWhere('knotenId', $this->knoten('ipa'));
        $this->assertNotNull($ipa);
        $this->assertSame(Zielrechner::BENOETIGT, $ipa['status']);
        $this->assertStringStartsWith('benötigt', $ipa['text']);

        $paket = $this->actingAs($this->user)->get(route('learner.qualification.index'))->assertOk();
        $html = $paket->getContent();
        $satz = $paket->viewData('statistik')['zusammenfassung']['positionen'];
        $this->assertSame('3 offene Positionen, 0 davon bestanden.', $satz);
        $this->assertStringContainsString('Was fehlt zum Bestehen', $html);
        $this->assertStringContainsString(e($satz), $html);
        $this->assertStringContainsString('<details', $html);
    }

    #[Test]
    public function erfasste_position_faellt_aus_der_liste(): void
    {
        NotenbaumPosition::create([
            'lernender_id' => $this->lernenderId,
            'knoten_id' => $this->knoten('ipa'),
            'note_wert' => 5.0,
            'erfasst_von_benutzer_id' => $this->user->benutzer_id,
        ]);

        $zeilen = $this->zeilen();

        $this->assertCount(2, $zeilen);
        $this->assertNull(collect($zeilen)->firstWhere('knotenId', $this->knoten('ipa')));
    }

    #[Test]
    public function nicht_zaehlende_position_erscheint_nicht(): void
    {
        DB::table('notenbaum_knoten')->where('code', 'ab_schlussarbeit')->update(['zaehlt' => 0]);

        $zeilen = $this->zeilen();

        $this->assertCount(2, $zeilen);
        $this->assertNull(collect($zeilen)->firstWhere('knotenId', $this->knoten('ab_schlussarbeit')));
    }

    #[Test]
    public function offene_positionen_veraendern_die_gemeinsame_ladung_nicht(): void
    {
        // Verlauf, Dashboard und Cockpit laden über lernender(): keine unbekannte Position darf dort auftauchen
        $geladen = app(StatistikDaten::class)->lernender($this->lernenderId);

        $this->assertSame([], array_filter($geladen['leistungen'], fn (Leistung $l) => $l->istPosition()));
    }

    #[Test]
    public function zeilen_der_benoetigten_note_tragen_die_pruefungs_id(): void
    {
        $k = Konfiguration::ausDb();
        $morgen = now()->addDay()->toDateString();
        $leistungen = [
            new Leistung(1, null, 5, null, $morgen, null, 40.0, Leistung::GEPLANT, 101, 'LB1'),
            new Leistung(1, null, 5, null, $morgen, null, 40.0, Leistung::GEPLANT, 102, 'LB1'),
        ];

        $zeilen = app(StatistikDaten::class)->pruefungen($leistungen, $k, 'alle')['diagramm']['zeilen'];

        $this->assertSame([101, 102], array_column($zeilen, 'id'));
    }
}
