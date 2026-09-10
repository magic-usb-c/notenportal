<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Betreuung;
use App\Models\Lehrberuf;
use App\Models\Note;
use App\Models\NotenGesehen;
use App\Models\NotenKommentar;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demodaten fuer Screenshots/Abnahme: 4 Lehrberufe, Semester 2022-2030,
 * 1 Admin, 3 Berufsbildner, 14 Lernende mit realistischen Notenverlaeufen.
 *
 * Deterministisch: fester Faker-Seed + fixes Referenzdatum (REFERENZDATUM),
 * damit zwei Laeufe dieselben Daten erzeugen.
 *
 * Darf NUR gegen eine *_test- oder *_demo-Datenbank laufen (siehe pruefeDatenbank()).
 */
class DemoSeeder extends Seeder
{
    private const REFERENZDATUM = '2026-09-15';

    private const DEMO_PASSWORT = 'Demo!2026';

    private const LEHRBERUFE = [
        ['kuerzel' => 'INPE', 'name' => 'Informatiker/in EFZ Plattformentwicklung'],
        ['kuerzel' => 'INAP', 'name' => 'Informatiker/in EFZ Applikationsentwicklung'],
        ['kuerzel' => 'EDB', 'name' => 'Entwickler/in digitales Business EFZ'],
        ['kuerzel' => 'INBE', 'name' => 'Betriebsinformatiker/in EFZ'],
    ];

    private const MODUL_ANZAHL = ['INPE' => 8, 'INAP' => 8, 'EDB' => 6, 'INBE' => 7];

    private const MODUL_NUMMER_BASIS = ['INPE' => 100, 'INAP' => 200, 'EDB' => 300, 'INBE' => 400];

    private const MODUL_THEMEN = [
        'Datenbanken abfragen', 'Netzwerke konfigurieren', 'Software testen',
        'Benutzeroberflächen gestalten', 'Projekte planen', 'Daten sichern',
        'Systeme dokumentieren', 'Support leisten', 'Prozesse analysieren',
        'Schnittstellen entwickeln',
    ];

    private const FAECHER = [
        'BMS' => ['Deutsch' => 'D', 'Englisch' => 'E', 'Mathematik' => 'M', 'Wirtschaft' => 'W'],
        'ABU' => ['Sprache und Kommunikation' => 'SK', 'Gesellschaft' => 'G'],
    ];

    private const NAMEN = [
        ['Laura', 'Frei'], ['Michael', 'Baumann'], ['Sarah', 'Keller'], ['David', 'Steiner'],
        ['Nina', 'Huber'], ['Marco', 'Meier'], ['Elena', 'Fischer'], ['Lukas', 'Weber'],
        ['Julia', 'Brunner'], ['Simon', 'Zimmermann'], ['Anna', 'Graf'], ['Thomas', 'Widmer'],
        ['Sara', 'Roth'], ['Pascal', 'Bachmann'], ['Nadine', 'Moser'], ['Fabian', 'Wyss'],
        ['Chiara', 'Kunz'], ['Reto', 'Hofer'],
    ];

    // Index 0 = Admin, 1-3 = Berufsbildner, 4-17 = Lernende (passend zu diesem Array).
    private const LERNENDE_KONFIG = [
        ['lehrberuf' => 'INPE', 'jahr' => 2022, 'track' => 'BMS', 'pattern' => 'stetig_besser'],
        ['lehrberuf' => 'INPE', 'jahr' => 2023, 'track' => 'ABU', 'pattern' => null],
        ['lehrberuf' => 'INPE', 'jahr' => 2024, 'track' => 'BMS', 'pattern' => null],
        ['lehrberuf' => 'INPE', 'jahr' => 2025, 'track' => 'ABU', 'pattern' => null],
        ['lehrberuf' => 'INAP', 'jahr' => 2022, 'track' => 'ABU', 'pattern' => 'einbruch'],
        ['lehrberuf' => 'INAP', 'jahr' => 2023, 'track' => 'BMS', 'pattern' => null],
        ['lehrberuf' => 'INAP', 'jahr' => 2024, 'track' => 'ABU', 'pattern' => null],
        ['lehrberuf' => 'INAP', 'jahr' => 2025, 'track' => 'BMS', 'pattern' => null],
        ['lehrberuf' => 'EDB', 'jahr' => 2022, 'track' => 'BMS', 'pattern' => 'stabil_gut'],
        ['lehrberuf' => 'EDB', 'jahr' => 2023, 'track' => 'ABU', 'pattern' => null],
        ['lehrberuf' => 'EDB', 'jahr' => 2025, 'track' => 'BMS', 'pattern' => null],
        ['lehrberuf' => 'INBE', 'jahr' => 2023, 'track' => 'BMS', 'pattern' => 'knapp_ungenuegend'],
        ['lehrberuf' => 'INBE', 'jahr' => 2024, 'track' => 'ABU', 'pattern' => null],
        ['lehrberuf' => 'INBE', 'jahr' => 2025, 'track' => 'ABU', 'pattern' => null],
    ];

    private const KOMMENTARE = [
        'Gut gemacht, weiter so.',
        'Bitte die Herleitung beim nächsten Mal genauer zeigen.',
        'Solide Leistung, sauber dokumentiert.',
        'Den Fehler in der Berechnung bitte nochmals anschauen.',
        'Deutliche Verbesserung gegenüber dem letzten Test.',
        'Knapp, aber bestanden. Am Ball bleiben.',
    ];

    private int $gewichtIndex = 0;

    public function run(): void
    {
        $this->pruefeDatenbank();
        fake()->seed(2026);

        $this->call(BasisSeeder::class);

        $lehrberufIds = $this->seedeLehrberufe();
        $faecher = $this->seedeFaecher();
        $module = $this->seedeModule($lehrberufIds);
        $this->seedeLehrberufFaecher($lehrberufIds, $faecher);
        $semester = $this->seedeSemester();
        $kategorieIds = DB::table('kategorien')->pluck('kategorie_id', 'code')->all();

        $personen = $this->seedePersonen($lehrberufIds);
        $this->seedeBetreuungen($personen);
        $this->seedeTracksModuleNoten($personen, $semester, $module, $faecher, $kategorieIds);
    }

    /**
     * RefreshDatabase droppt/aendert Tabellen destruktiv – nie gegen die Produktiv-DB laufen.
     */
    private function pruefeDatenbank(): void
    {
        $name = DB::connection()->getDatabaseName();

        if (! str_ends_with($name, '_test') && ! str_ends_with($name, '_demo')) {
            throw new RuntimeException("DemoSeeder darf nur gegen eine *_test- oder *_demo-Datenbank laufen, nicht gegen «{$name}».");
        }
    }

    private function seedeLehrberufe(): array
    {
        $ids = [];
        foreach (self::LEHRBERUFE as $lb) {
            $model = Lehrberuf::query()->firstOrCreate(
                ['kuerzel' => $lb['kuerzel']],
                ['name' => $lb['name'], 'aktiv' => true]
            );
            $ids[$lb['kuerzel']] = $model->lehrberuf_id;
        }

        return $ids;
    }

    /** @return array{BMS: array<int, array{fach_id:int, name:string}>, ABU: array<int, array{fach_id:int, name:string}>} */
    private function seedeFaecher(): array
    {
        $out = ['BMS' => [], 'ABU' => []];

        foreach (self::FAECHER as $track => $liste) {
            foreach ($liste as $name => $kurz) {
                $id = DB::table('faecher')->where('track_typ', $track)->where('name', $name)->value('fach_id');
                if (! $id) {
                    $id = DB::table('faecher')->insertGetId([
                        'track_typ' => $track, 'name' => $name, 'kurzname' => $kurz, 'aktiv' => 1,
                    ]);
                }
                $out[$track][] = ['fach_id' => $id, 'name' => $name];
            }
        }

        return $out;
    }

    /** @return array<string, array<int, array{modul_id:int, nr:int}>> */
    private function seedeModule(array $lehrberufIds): array
    {
        $out = [];

        foreach (self::LEHRBERUFE as $lb) {
            $kuerzel = $lb['kuerzel'];
            $anzahl = self::MODUL_ANZAHL[$kuerzel];
            $basis = self::MODUL_NUMMER_BASIS[$kuerzel];
            $out[$kuerzel] = [];

            for ($i = 1; $i <= $anzahl; $i++) {
                $nummer = 'D'.($basis + $i);
                $thema = self::MODUL_THEMEN[($i - 1) % count(self::MODUL_THEMEN)];

                $modulId = DB::table('module')->where('modul_nummer', $nummer)->value('modul_id');
                if (! $modulId) {
                    $modulId = DB::table('module')->insertGetId([
                        'modul_nummer' => $nummer,
                        'titel' => 'Demo: '.$thema,
                        'ziel_gewicht_summe_default' => 100.00,
                        'aktiv' => 1,
                    ]);
                }

                $nr = (int) min(8, max(1, (int) ceil($i * 8 / $anzahl)));

                DB::table('lehrberuf_module')->updateOrInsert(
                    ['lehrberuf_id' => $lehrberufIds[$kuerzel], 'modul_id' => $modulId],
                    ['pflicht' => true, 'empfohlenes_lehrsemester_nr' => $nr, 'aktiv' => 1]
                );

                $out[$kuerzel][] = ['modul_id' => $modulId, 'nr' => $nr];
            }
        }

        return $out;
    }

    private function seedeLehrberufFaecher(array $lehrberufIds, array $faecher): void
    {
        foreach ($lehrberufIds as $id) {
            foreach (['BMS', 'ABU'] as $track) {
                foreach ($faecher[$track] as $f) {
                    DB::table('lehrberuf_faecher')->updateOrInsert(
                        ['lehrberuf_id' => $id, 'fach_id' => $f['fach_id']],
                        ['aktiv' => 1]
                    );
                }
            }
        }
    }

    /** Semester Aug./Feb. fuer die Schuljahre 2022/23 bis 2029/30. */
    private function seedeSemester(): Collection
    {
        $out = collect();
        $sort = 1;

        for ($jahr = 2022; $jahr <= 2029; $jahr++) {
            $kurzA = substr((string) $jahr, 2, 2);
            $kurzB = substr((string) ($jahr + 1), 2, 2);

            $reihen = [
                ['bezeichnung' => "{$kurzA}/{$kurzB}-1", 'start_datum' => "{$jahr}-08-01", 'end_datum' => ($jahr + 1).'-01-31'],
                ['bezeichnung' => "{$kurzA}/{$kurzB}-2", 'start_datum' => ($jahr + 1).'-02-01', 'end_datum' => ($jahr + 1).'-07-31'],
            ];

            foreach ($reihen as $r) {
                $r['sortierung'] = $sort++;
                $id = DB::table('semester')->where('bezeichnung', $r['bezeichnung'])->value('semester_id');
                if (! $id) {
                    $id = DB::table('semester')->insertGetId($r);
                }
                $out->push((object) array_merge($r, ['semester_id' => $id]));
            }
        }

        return $out->sortBy('sortierung')->values();
    }

    /** @return array{admin: User, bbs: array<int, User>, lernende: array<int, array<string, mixed>>} */
    private function seedePersonen(array $lehrberufIds): array
    {
        $namen = self::NAMEN;
        $idx = 0;

        $macheBenutzer = static function (array $paar): array {
            [$vorname, $nachname] = $paar;
            $slug = strtolower($vorname).'.'.strtolower($nachname);

            return [
                'vorname' => $vorname,
                'nachname' => $nachname,
                'benutzername' => $slug,
                'email' => $slug.'@demo.example',
            ];
        };

        $adminDaten = $macheBenutzer($namen[$idx++]);
        $admin = User::factory()->admin()->create(array_merge($adminDaten, ['passwort_hash' => self::DEMO_PASSWORT]));

        $bbs = [];
        for ($i = 0; $i < 3; $i++) {
            $d = $macheBenutzer($namen[$idx++]);
            $bbs[] = User::factory()->berufsbildner()->create(array_merge($d, ['passwort_hash' => self::DEMO_PASSWORT]));
        }

        $lernende = [];
        foreach (self::LERNENDE_KONFIG as $cfg) {
            $d = $macheBenutzer($namen[$idx++]);
            $jahr = $cfg['jahr'];
            $lehrbeginn = "{$jahr}-08-01";
            $lehrende = ($jahr + 4).'-07-31';

            $user = User::factory()->lernender([
                'lehrberuf_id' => $lehrberufIds[$cfg['lehrberuf']],
                'lehrbeginn' => $lehrbeginn,
                'lehrende' => $lehrende,
            ])->create(array_merge($d, ['passwort_hash' => self::DEMO_PASSWORT]));

            $lernende[] = [
                'user' => $user,
                'lernender' => $user->lernender,
                'lehrberuf' => $cfg['lehrberuf'],
                'lehrbeginn' => $lehrbeginn,
                'lehrende' => $lehrende,
                'track' => $cfg['track'],
                'pattern' => $cfg['pattern'],
            ];
        }

        return ['admin' => $admin, 'bbs' => $bbs, 'lernende' => $lernende];
    }

    /**
     * Jede/r Lernende bekommt genau eine aktive Betreuung + eine abgelaufene (Wechsel des BB).
     */
    private function seedeBetreuungen(array &$personen): void
    {
        $bbs = $personen['bbs'];

        foreach ($personen['lernende'] as $i => &$l) {
            $lernenderId = $l['lernender']->lernender_id;
            $aktivBb = $bbs[$i % 3];
            $altBb = $bbs[($i + 1) % 3];
            $wechsel = Carbon::parse($l['lehrbeginn'])->addDays(182);

            Betreuung::create([
                'berufsbildner_id' => $altBb->berufsbildner->berufsbildner_id,
                'lernender_id' => $lernenderId,
                'gueltig_von' => $l['lehrbeginn'],
                'gueltig_bis' => $wechsel->toDateString(),
            ]);

            Betreuung::create([
                'berufsbildner_id' => $aktivBb->berufsbildner->berufsbildner_id,
                'lernender_id' => $lernenderId,
                'gueltig_von' => $wechsel->copy()->addDay()->toDateString(),
                'gueltig_bis' => null,
            ]);

            $l['aktiver_bb_benutzer_id'] = $aktivBb->benutzer_id;
        }
        unset($l);
    }

    /**
     * Tracks, Modulbelegungen und Noten je Lernender – inkl. Kommentare, gesehen-Markierungen
     * und ein paar soft-deleted Noten.
     */
    private function seedeTracksModuleNoten(array $personen, Collection $semesterAlle, array $module, array $faecher, array $kategorieIds): void
    {
        $this->gewichtIndex = 0;
        $kommentarIndex = 0;

        foreach ($personen['lernende'] as $i => $l) {
            $lernenderId = $l['lernender']->lernender_id;
            $benutzerId = $l['user']->benutzer_id;
            $aktivBbBenutzerId = $l['aktiver_bb_benutzer_id'];
            $lehrbeginn = $l['lehrbeginn'];
            $lehrende = $l['lehrende'];
            $capDate = min(self::REFERENZDATUM, $lehrende);

            $aktiveSemester = $semesterAlle->filter(
                fn ($s) => $s->start_datum >= $lehrbeginn && $s->start_datum <= $capDate
            )->values();

            if ($aktiveSemester->isEmpty()) {
                continue;
            }

            $startSem = $this->semesterFuerDatum($semesterAlle, $lehrbeginn);
            $trackBeendet = $capDate === $lehrende;
            $endSem = $trackBeendet ? $this->semesterFuerDatum($semesterAlle, $lehrende) : null;

            DB::table('lernender_tracks')->insert([
                'lernender_id' => $lernenderId,
                'track_typ' => $l['track'],
                'start_datum' => $lehrbeginn,
                'end_datum' => $trackBeendet ? $lehrende : null,
                'start_semester_id' => $startSem->semester_id,
                'end_semester_id' => $endSem?->semester_id,
            ]);

            $notenDesLernenden = [];

            // Modul-Noten: FACH (Fachunterricht) + UEK, je belegtes Modul.
            foreach ($module[$l['lehrberuf']] as $m) {
                if ($m['nr'] > $aktiveSemester->count()) {
                    continue;
                }
                $semDesModuls = $aktiveSemester[$m['nr'] - 1];

                $belegungId = DB::table('modul_belegungen')->insertGetId([
                    'lernender_id' => $lernenderId,
                    'modul_id' => $m['modul_id'],
                    'start_datum' => $semDesModuls->start_datum,
                    'end_datum' => null,
                ]);

                [$zentrum, $spread] = $this->zentrumUndSpread($l['pattern'], $m['nr'], $aktiveSemester->count(), false);

                foreach ([25, 70] as $offset) {
                    $notenDesLernenden[] = $this->erstelleNote([
                        'lernender_id' => $lernenderId,
                        'kategorie_id' => $kategorieIds['FACH'],
                        'semester_id' => $semDesModuls->semester_id,
                        'fach_id' => null,
                        'modul_belegung_id' => $belegungId,
                        'pruefungsdatum' => $this->datumInSemester($semDesModuls, $offset, $capDate),
                        'note_wert' => $this->wert($lernenderId * 97 + $belegungId + $offset, $zentrum, $spread),
                        'erfasst_von_benutzer_id' => $benutzerId,
                    ]);
                }

                $notenDesLernenden[] = $this->erstelleNote([
                    'lernender_id' => $lernenderId,
                    'kategorie_id' => $kategorieIds['UEK'],
                    'semester_id' => $semDesModuls->semester_id,
                    'fach_id' => null,
                    'modul_belegung_id' => $belegungId,
                    'pruefungsdatum' => $this->datumInSemester($semDesModuls, 45, $capDate),
                    'note_wert' => $this->wert($lernenderId * 53 + $belegungId, $zentrum, $spread),
                    'erfasst_von_benutzer_id' => $benutzerId,
                ]);
            }

            // Fach-Noten: BMS oder ABU, je nach Track des Lernenden.
            $faecherDesTracks = $faecher[$l['track']];
            $letzterIndex = $aktiveSemester->count() - 1;

            foreach ($aktiveSemester as $si => $sem) {
                foreach ($faecherDesTracks as $fi => $fach) {
                    $istDip = $si === $letzterIndex && $fi === 0;
                    [$zentrum, $spread] = $this->zentrumUndSpread($l['pattern'], $si + 1, $aktiveSemester->count(), $istDip);

                    $notenDesLernenden[] = $this->erstelleNote([
                        'lernender_id' => $lernenderId,
                        'kategorie_id' => $kategorieIds[$l['track']],
                        'semester_id' => $sem->semester_id,
                        'fach_id' => $fach['fach_id'],
                        'modul_belegung_id' => null,
                        'pruefungsdatum' => $this->datumInSemester($sem, 20 + $fi * 15, $capDate),
                        'note_wert' => $this->wert($lernenderId * 71 + $sem->semester_id + $fach['fach_id'], $zentrum, $spread),
                        'erfasst_von_benutzer_id' => $benutzerId,
                    ]);
                }
            }

            // Kommentare + gesehen-Markierungen durch die aktuell aktive Betreuungsperson.
            foreach ($notenDesLernenden as $ni => $note) {
                if ($ni % 3 === 0) {
                    NotenGesehen::create([
                        'note_id' => $note->note_id,
                        'viewer_benutzer_id' => $aktivBbBenutzerId,
                        'gesehen_am' => Carbon::parse($note->pruefungsdatum)->addDays(3),
                    ]);
                }
                if ($ni % 6 === 0) {
                    NotenKommentar::create([
                        'note_id' => $note->note_id,
                        'autor_benutzer_id' => $aktivBbBenutzerId,
                        'kommentar_text' => self::KOMMENTARE[$kommentarIndex % count(self::KOMMENTARE)],
                    ]);
                    $kommentarIndex++;
                }
            }

            // Ein paar Noten soft-deleted (Beispiel fuer geloeschte Eintraege).
            if ($i % 4 === 2 && $notenDesLernenden !== []) {
                $notenDesLernenden[0]->delete();
            }
        }
    }

    private function semesterFuerDatum(Collection $semester, string $datum): object
    {
        return $semester->first(fn ($s) => $s->start_datum <= $datum && $s->end_datum >= $datum);
    }

    private function datumInSemester(object $semester, int $offsetTage, string $kappungsDatum): string
    {
        $d = Carbon::parse($semester->start_datum)->addDays($offsetTage);
        $ende = Carbon::parse($semester->end_datum);
        $kappung = Carbon::parse($kappungsDatum);

        if ($d->gt($ende)) {
            $d = $ende->copy();
        }
        if ($d->gt($kappung)) {
            $d = $kappung->copy();
        }

        return $d->toDateString();
    }

    /** @return array{0: float, 1: float} [Zentrum, Streuung] je nach erkennbarem Muster. */
    private function zentrumUndSpread(?string $pattern, int $semesterNr, int $anzahlSemester, bool $istDip): array
    {
        return match ($pattern) {
            'stetig_besser' => [min(5.8, 3.8 + 0.35 * ($semesterNr - 1)), 0.15],
            'einbruch' => $istDip ? [3.0, 0.15] : [4.8, 0.2],
            'stabil_gut' => [5.3, 0.1],
            'knapp_ungenuegend' => [3.8, 0.1],
            default => [4.5, 0.4],
        };
    }

    /**
     * Deterministischer Pseudo-Zufallswert um ein Zentrum, unabhaengig von der Aufrufreihenfolge.
     * Note_wert ist decimal(3,1) in der DB -> nur 0.5er-Schritte sind exakt speicherbar
     * (0.25er-Schritte wuerden auf 0.1 gerundet, siehe Bericht am Ende).
     */
    private function wert(int $seed, float $zentrum, float $spread): float
    {
        $x = sin($seed * 12.9898) * 43758.5453;
        $anteil = $x - floor($x);
        $delta = ($anteil - 0.5) * 2 * $spread;
        $wert = max(1.0, min(6.0, $zentrum + $delta));

        return round($wert * 2) / 2;
    }

    private function erstelleNote(array $attribute): Note
    {
        $gewichtungen = [100.0, 50.0, 25.0, null];
        $gewicht = $gewichtungen[$this->gewichtIndex % 4];
        $this->gewichtIndex++;

        return Note::create(array_merge([
            'gruppe_id' => null,
            'titel' => null,
            'gewichtung_prozent' => $gewicht,
            'aktualisiert_von_benutzer_id' => null,
        ], $attribute));
    }
}
