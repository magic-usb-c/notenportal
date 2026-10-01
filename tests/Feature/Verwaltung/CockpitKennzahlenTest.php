<?php

declare(strict_types=1);

namespace Tests\Feature\Verwaltung;

use App\Models\Fach;
use App\Models\Note;
use App\Models\Pruefung;
use App\Support\Format;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kennzahlenleiste im Cockpit: nächste Prüfung ist die nächste künftige ohne Note (nicht die überfällige),
 * letzte Prüfung das Datum der jüngsten Note, je mit dem Abstand zu heute.
 */
class CockpitKennzahlenTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function zeigt_naechste_und_letzte_pruefung_mit_abstand(): void
    {
        $lernender = $this->neuerLernender();
        $bb = $this->verwalter('trainer', $lernender);
        $fach = fn (string $name) => Fach::factory()->create(['name' => $name])->fach_id;
        $pruefung = fn (string $name, int $tage) => Pruefung::create([
            'lernender_id' => $lernender->lernender_id, 'fach_id' => $fach($name), 'datum' => now()->addDays($tage)->toDateString(),
            'gewichtung_prozent' => 100, 'quelle' => Pruefung::MANUELL,
        ]);
        $pruefung('Überfälliges Fach', -4);
        $naechste = $pruefung('Nächstes Fach', 3);
        $pruefung('Späteres Fach', 9);
        $letzte = Note::factory()->create(['lernender_id' => $lernender->lernender_id, 'pruefungsdatum' => now()->subDays(6)->toDateString()]);

        $this->actingAs($bb)->get(route('trainer.learners.show', $lernender->lernender_id))
            ->assertOk()
            ->assertSeeInOrder([
                __('Nächste Prüfung'), Format::date($naechste->datum, 'tag_monat'), 'Nächstes Fach · '.__('in :anzahl Tagen', ['anzahl' => 3]),
                __('Letzte Prüfung'), Format::date($letzte->pruefungsdatum, 'tag_monat'), __('vor :anzahl Tagen', ['anzahl' => 6]),
            ]);
    }
}
