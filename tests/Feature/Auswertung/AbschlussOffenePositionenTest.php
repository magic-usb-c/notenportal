<?php

declare(strict_types=1);

namespace Tests\Feature\Auswertung;

use App\Models\NotenbaumPosition;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\Notenbaum\Baum;
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

    private function zusammenfassung(): string
    {
        return $this->actingAs($this->user)->get(route('learner.qualification.index'))
            ->assertOk()->viewData('statistik')['zusammenfassung']['positionen'];
    }

    private function position(string $code, float $wert): void
    {
        NotenbaumPosition::create([
            'lernender_id' => $this->lernenderId,
            'knoten_id' => $this->knoten($code),
            'note_wert' => $wert,
            'erfasst_von_benutzer_id' => $this->user->benutzer_id,
        ]);
    }

    /** @param  array<string, mixed>  $werte */
    private function knotenAendern(string $code, array $werte): void
    {
        DB::table('notenbaum_knoten')->where('code', $code)->update($werte);
        Konfiguration::vergessen();
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
    public function ipa_unter_der_fallnote_macht_jede_offene_position_unerreichbar_und_nennt_den_grund(): void
    {
        // Ohne Bestehensregel bräuchten Schlussarbeit und Schlussprüfung für die Gesamtnote 4.85; die IPA ist aber endgültig ungenügend
        $this->position('ipa', 3.5);

        $zeilen = $this->zeilen();

        $this->assertCount(2, $zeilen);
        foreach ($zeilen as $zeile) {
            $this->assertSame(Zielrechner::UNERREICHBAR, $zeile['status']);
            $this->assertSame('nicht erreichbar', $zeile['text']);
        }
        $this->assertSame('Bestehen nicht mehr möglich: Praktische Arbeit (IPA) unter 4.0.', $this->zusammenfassung());
    }

    #[Test]
    public function offene_ipa_braucht_ihre_fallnote_auch_wenn_die_gesamtnote_weniger_verlangt(): void
    {
        // Mit 6.0 in Schlussarbeit und Schlussprüfung reichten für die Gesamtnote 2.95 in der IPA, ihre Fallnote verlangt 4.0
        $this->position('ab_schlussarbeit', 6.0);
        $this->position('ab_schlusspruefung', 6.0);

        $zeilen = $this->zeilen();

        $this->assertSame([$this->knoten('ipa')], array_column($zeilen, 'knotenId'));
        $this->assertSame(Zielrechner::BENOETIGT, $zeilen[0]['status']);
        $this->assertEquals(4.0, $zeilen[0]['note']);
        $this->assertSame('benötigt 4.00', $zeilen[0]['text']);
    }

    #[Test]
    public function position_unter_einer_nicht_zaehlenden_gruppe_erscheint_nicht(): void
    {
        $this->knotenAendern('ab', ['zaehlt' => 0]);

        $this->assertSame([$this->knoten('ipa')], array_column($this->zeilen(), 'knotenId'));
    }

    #[Test]
    public function position_ohne_gewicht_mit_eigener_fallnote_erscheint_mit_der_fallnote(): void
    {
        // Gewicht 0 bewegt die Note nicht, die eigene Mindestnote entscheidet trotzdem übers Bestehen
        $this->knotenAendern('ab_schlussarbeit', ['gewicht' => 0, 'fallnote' => 4.5]);
        $this->position('ipa', 5.0);
        $this->position('ab_schlusspruefung', 5.0);

        $zeilen = $this->zeilen();

        $this->assertSame([$this->knoten('ab_schlussarbeit')], array_column($zeilen, 'knotenId'));
        $this->assertSame(Zielrechner::BENOETIGT, $zeilen[0]['status']);
        $this->assertSame('benötigt 4.50', $zeilen[0]['text']);
    }

    #[Test]
    public function position_ohne_gewicht_und_ohne_regel_erscheint_nicht(): void
    {
        $this->knotenAendern('ab_schlussarbeit', ['gewicht' => 0]);

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

    #[Test]
    public function regel_aus_dem_heutigen_stand_macht_offene_positionen_unerreichbar_ohne_endgueltig_zu_sein(): void
    {
        $fach = (int) DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id');
        $paket = fn (array $wurzel, array $leistungen) => app(StatistikDaten::class)->positionen($leistungen,
            Konfiguration::ausDb()->mitBaeumen([Baum::ausArray(900, 'QV', ['id' => 9001, 'code' => 'qv', 'typ' => 'gruppe', 'rundung' => 0.1, ...$wurzel])]));

        // Informatikkompetenzen 3.0 unter ihrer Fallnote: keine IPA-Note hilft, die Module können sich aber noch ändern
        $p = $paket(['fallnote' => 4.0, 'kinder' => [
            ['id' => 9002, 'code' => 'ipa', 'name' => 'IPA', 'typ' => 'manuell'],
            ['id' => 9003, 'code' => 'ik', 'name' => 'Informatikkompetenzen', 'typ' => 'kategorie', 'kategorie_id' => $fach, 'fallnote' => 4.0],
        ]], [new Leistung($fach, null, 9100, null, '2025-01-10', 3.0), Leistung::position(9002, null)]);

        $this->assertSame(['nicht erreichbar'], array_column($p['diagramm']['zeilen'], 'text'));
        $this->assertSame('Bestehen mit dem heutigen Stand nicht erreichbar: Informatikkompetenzen unter 4.0.', $p['zusammenfassung']);

        // Grenze für ungenügende Teile: der Grund steht mit seinem eigenen Text im Satz
        $p = $paket(['name' => 'QV', 'max_ungenuegend' => 0, 'kinder' => [
            ['id' => 9002, 'code' => 'a', 'typ' => 'manuell'],
            ['id' => 9003, 'code' => 'b', 'typ' => 'manuell'],
        ]], [Leistung::position(9002, 3.5), Leistung::position(9003, null)]);

        $this->assertSame('Bestehen mit dem heutigen Stand nicht erreichbar: QV: 1 ungenügende Noten, erlaubt sind 0.', $p['zusammenfassung']);
    }
}
