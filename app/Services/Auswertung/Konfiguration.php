<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use App\Models\Semester;
use App\Support\Einstellungen;
use App\Support\Lehrsemester;
use Illuminate\Support\Facades\DB;

/**
 * Rechenregeln und Stammdaten, die der Rechenkern braucht (aus der DB oder im Test von Hand).
 *
 * @phpstan-type KategorieRegel array{code: string, name: string, sortierung: int, rundung_element: float,
 *     rundung_schnitt: float, gewicht_gesamt: float, promotion_min_schnitt: ?float,
 *     promotion_max_ungenuegend: ?int, promotion_max_minuspunkte: ?float}
 */
final class Konfiguration
{
    private static ?self $ausDb = null;

    /**
     * @param  array<int, KategorieRegel>  $kategorien
     * @param  array<int, array{bezeichnung: string, sortierung: int, start: string, ende: string}>  $semester
     * @param  array<int, string>  $faecher
     * @param  array<int, array{nummer: string, titel: string, ziel: ?float}>  $module
     */
    public function __construct(
        public readonly array $kategorien,
        public readonly array $semester,
        public readonly array $faecher = [],
        public readonly array $module = [],
        public readonly float $genuegend = 4.0,
        public readonly float $rundungGesamt = 0.1,
    ) {}

    public static function ausDb(): self
    {
        return self::$ausDb ??= new self(
            kategorien: DB::table('kategorien')->orderBy('sortierung')->get()->mapWithKeys(fn ($k) => [(int) $k->kategorie_id => [
                'code' => $k->code,
                'name' => $k->name,
                'sortierung' => (int) $k->sortierung,
                'rundung_element' => (float) $k->rundung_element,
                'rundung_schnitt' => (float) $k->rundung_schnitt,
                'gewicht_gesamt' => (float) $k->gewicht_gesamt,
                'promotion_min_schnitt' => $k->promotion_min_schnitt !== null ? (float) $k->promotion_min_schnitt : null,
                'promotion_max_ungenuegend' => $k->promotion_max_ungenuegend !== null ? (int) $k->promotion_max_ungenuegend : null,
                'promotion_max_minuspunkte' => $k->promotion_max_minuspunkte !== null ? (float) $k->promotion_max_minuspunkte : null,
            ]])->all(),
            semester: DB::table('semester')->orderBy('sortierung')->get()->mapWithKeys(fn ($s) => [(int) $s->semester_id => [
                'bezeichnung' => $s->bezeichnung,
                'sortierung' => (int) $s->sortierung,
                'start' => (string) $s->start_datum,
                'ende' => (string) $s->end_datum,
            ]])->all(),
            faecher: DB::table('faecher')->pluck('name', 'fach_id')->mapWithKeys(fn ($n, $id) => [(int) $id => (string) $n])->all(),
            module: DB::table('module')->get()->mapWithKeys(fn ($m) => [(int) $m->modul_id => [
                'nummer' => (string) $m->modul_nummer,
                'titel' => (string) $m->titel,
                'ziel' => $m->ziel_gewicht_summe_default !== null ? (float) $m->ziel_gewicht_summe_default : null,
            ]])->all(),
            genuegend: (float) Einstellungen::get(Einstellungen::NOTE_GENUEGEND, '4.0'),
            rundungGesamt: (float) Einstellungen::get(Einstellungen::RUNDUNG_GESAMT, '0.1'),
        );
    }

    /** Nach Stammdaten-Änderungen im selben Request (Tests, Einrichtung). */
    public static function vergessen(): void
    {
        self::$ausDb = null;
    }

    public function semesterFuerDatum(string $datum): ?int
    {
        foreach ($this->semester as $id => $s) {
            if ($s['start'] <= $datum && $s['ende'] >= $datum) {
                return $id;
            }
        }

        return null;
    }

    public function semesterSortierung(?int $id): int
    {
        return $id !== null ? ($this->semester[$id]['sortierung'] ?? 0) : 0;
    }

    /**
     * Anzeigename eines Semesters. Mit $lernenderId (Ansicht für genau einen Lernenden):
     * relative Semesternummer «5. Semester». Ohne (Listen über mehrere Lernende, Stammdaten):
     * neutraler Name «HS 2026/27». Fällt auf den Code zurück, wenn beides fehlt.
     */
    public function semesterName(?int $id, ?int $lernenderId = null): string
    {
        if ($id === null || ! isset($this->semester[$id])) {
            return '–';
        }

        $sem = $this->semester[$id];

        // Rechenkern bleibt frei von __(): reine Unit-Tests (ohne Laravel-App, z. B.
        // RechenkernTest) rufen toArray()/verlauf() ohne gebundenen Translator auf.
        // Dort bleibt der Rohcode stehen; die echte Anzeige läuft stets über die App.
        if (! app()->bound('translator')) {
            return $sem['bezeichnung'];
        }

        if ($lernenderId !== null) {
            $n = Lehrsemester::nummer($lernenderId, $id);
            if ($n !== null) {
                return Lehrsemester::name($n);
            }
        }

        return Semester::neutralerName($sem['start']) ?? $sem['bezeichnung'];
    }

    public function kategorieName(int $id): string
    {
        return $this->kategorien[$id]['name'] ?? '–';
    }

    public function rundungElement(int $kategorieId): float
    {
        return $this->kategorien[$kategorieId]['rundung_element'] ?? 0.5;
    }

    public function rundungSchnitt(int $kategorieId): float
    {
        return $this->kategorien[$kategorieId]['rundung_schnitt'] ?? 0.1;
    }

    public function fachName(int $id): string
    {
        return $this->faecher[$id] ?? 'Fach';
    }

    public function modulName(int $id): string
    {
        $m = $this->module[$id] ?? null;

        return $m ? trim($m['nummer'].' '.$m['titel']) : 'Modul';
    }
}
