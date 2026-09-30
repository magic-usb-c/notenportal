<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Betreuung;
use App\Models\Lehrberuf;
use App\Models\Note;
use App\Models\NotenGesehen;
use App\Models\NotenKommentar;
use App\Models\User;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demodaten für Abnahme und Screenshots: 4 Lehrberufe mit Schul- und ÜK-Modulen, BMS-/ABU-Fächer,
 * 1 Admin, 3 Berufsbildner, 14 Lernende vom 1. bis 4. Lehrjahr mit erkennbaren Verläufen,
 * geplanten Prüfungen, Zielen, Kommentaren.
 *
 * Deterministisch (fixes Referenzdatum, Pseudozufall aus Hash). Nur gegen *_test oder *_demo.
 */
class DemoSeeder extends Seeder
{
    private const string REFERENZDATUM = '2026-09-08';

    private const string DEMO_PASSWORT = 'Demo!2026';

    private const array LEHRBERUFE = [
        'INPE' => ['name' => 'Informatiker/in EFZ Plattformentwicklung', 'basis' => 100],
        'INAP' => ['name' => 'Informatiker/in EFZ Applikationsentwicklung', 'basis' => 200],
        'EDB' => ['name' => 'Entwickler/in digitales Business EFZ', 'basis' => 300],
        'INBE' => ['name' => 'Betriebsinformatiker/in EFZ', 'basis' => 400],
    ];

    private const array SCHUL_THEMEN = [
        'Daten modellieren', 'Netzwerke aufbauen', 'Software testen', 'Oberflächen gestalten', 'Projekte planen',
        'Daten sichern', 'Systeme dokumentieren', 'Schnittstellen entwickeln', 'Prozesse analysieren',
        'Sicherheit umsetzen', 'Cloud betreiben', 'Daten auswerten',
    ];

    private const array UEK_THEMEN = ['Arbeitsplatz einrichten', 'Hardware in Betrieb nehmen', 'Kundensupport leisten', 'Web-Applikation umsetzen', 'Server betreiben'];

    private const array FAECHER = [
        'BMS' => ['Deutsch' => 'D', 'Englisch' => 'E', 'Italienisch' => 'I', 'Mathematik' => 'M', 'Wirtschaft und Recht' => 'WR', 'Naturwissenschaften' => 'NW'],
        'ABU' => ['Sprache und Kommunikation' => 'SK', 'Gesellschaft' => 'G'],
    ];

    private const array NAMEN = [
        ['Laura', 'Frei'], ['Michael', 'Baumann'], ['Sarah', 'Keller'], ['David', 'Steiner'],
        ['Nina', 'Huber'], ['Marco', 'Meier'], ['Elena', 'Fischer'], ['Lukas', 'Weber'],
        ['Julia', 'Brunner'], ['Simon', 'Zimmermann'], ['Anna', 'Graf'], ['Thomas', 'Widmer'],
        ['Sara', 'Roth'], ['Pascal', 'Bachmann'], ['Nadine', 'Moser'], ['Fabian', 'Wyss'],
        ['Chiara', 'Kunz'], ['Reto', 'Hofer'],
    ];

    /** Reihenfolge wie NAMEN ab Index 4. */
    private const array LERNENDE_KONFIG = [
        ['beruf' => 'INAP', 'jahr' => 2023, 'track' => 'BMS', 'muster' => 'stetig_besser', 'klasse' => 'INF23a'],
        ['beruf' => 'INPE', 'jahr' => 2023, 'track' => 'ABU', 'muster' => 'normal', 'klasse' => 'INF23b'],
        ['beruf' => 'INPE', 'jahr' => 2024, 'track' => 'BMS', 'muster' => 'einbruch', 'klasse' => 'INF24a'],
        ['beruf' => 'INPE', 'jahr' => 2025, 'track' => 'ABU', 'muster' => 'normal', 'klasse' => 'INF25b'],
        ['beruf' => 'INAP', 'jahr' => 2022, 'track' => 'ABU', 'muster' => 'stabil_gut', 'klasse' => 'INF22a'],
        ['beruf' => 'INAP', 'jahr' => 2024, 'track' => 'BMS', 'muster' => 'knapp', 'klasse' => 'INF24a'],
        ['beruf' => 'INAP', 'jahr' => 2025, 'track' => 'BMS', 'muster' => 'normal', 'klasse' => 'INF25a'],
        ['beruf' => 'EDB', 'jahr' => 2023, 'track' => 'BMS', 'muster' => 'stabil_gut', 'klasse' => 'EDB23'],
        ['beruf' => 'EDB', 'jahr' => 2024, 'track' => 'ABU', 'muster' => 'inaktiv', 'klasse' => 'EDB24'],
        ['beruf' => 'EDB', 'jahr' => 2026, 'track' => 'ABU', 'muster' => 'neu', 'klasse' => 'EDB26'],
        ['beruf' => 'INBE', 'jahr' => 2023, 'track' => 'BMS', 'muster' => 'schwankend', 'klasse' => 'INB23'],
        ['beruf' => 'INBE', 'jahr' => 2024, 'track' => 'ABU', 'muster' => 'normal', 'klasse' => 'INB24'],
        ['beruf' => 'INAP', 'jahr' => 2026, 'track' => 'BMS', 'muster' => 'neu', 'klasse' => 'INF26a'],
        ['beruf' => 'INPE', 'jahr' => 2025, 'track' => 'BMS', 'muster' => 'normal', 'klasse' => 'INF25a'],
    ];

    private const array KOMMENTARE = [
        'Gut gemacht, weiter so.',
        'Bitte die Herleitung beim nächsten Mal genauer zeigen.',
        'Solide Leistung, sauber dokumentiert.',
        'Den Fehler in der Berechnung bitte nochmals anschauen.',
        'Deutliche Verbesserung gegenüber dem letzten Test.',
        'Knapp, aber bestanden. Am Ball bleiben.',
    ];

    /** @var array<string, int> */
    private array $kategorien = [];

    public function run(): void
    {
        $this->pruefeDatenbank();
        $this->call(BasisSeeder::class);
        DB::table('einstellungen')->updateOrInsert(['schluessel' => 'betrieb_name'], ['wert' => 'Muster AG']);

        $this->kategorien = DB::table('kategorien')->pluck('kategorie_id', 'code')->map(fn ($id) => (int) $id)->all();
        $semester = $this->seedeSemester();
        $faecher = $this->seedeFaecher();
        $berufe = $this->seedeLehrberufeUndModule();
        $personen = $this->seedePersonen($berufe);

        $kommentar = 0;
        foreach ($personen['lernende'] as $i => $l) {
            $this->seedeLernenden($l, $i, $semester, $faecher, $berufe, $kommentar);
        }

        // QV-Notenbaum der beiden Informatiker-Fachrichtungen: zeigt Abschlussseite, QV-Prognose und die
        // Stammdaten-Ansicht. EDB und INBE bleiben ohne Baum und rechnen flach – beides soll sichtbar sein.
        foreach (['INPE', 'INAP'] as $kuerzel) {
            app(BaumVorlage::class)->importieren(BaumVorlage::laden('informatiker-efz-bivo2020'), $berufe[$kuerzel]['lehrberuf_id']);
        }
    }

    private function pruefeDatenbank(): void
    {
        $name = DB::connection()->getDatabaseName();

        if (! str_ends_with($name, '_test') && ! str_ends_with($name, '_demo')) {
            throw new RuntimeException("DemoSeeder darf nur gegen eine *_test- oder *_demo-Datenbank laufen, nicht gegen «{$name}».");
        }
    }

    /** Semester Aug./Feb. für die Schuljahre 2022/23 bis 2029/30. */
    private function seedeSemester(): Collection
    {
        $out = collect();
        $sort = 1;
        for ($jahr = 2022; $jahr <= 2029; $jahr++) {
            $a = substr((string) $jahr, 2, 2);
            $b = substr((string) ($jahr + 1), 2, 2);
            foreach ([
                ["{$a}/{$b}-1", "{$jahr}-08-01", ($jahr + 1).'-01-31'],
                ["{$a}/{$b}-2", ($jahr + 1).'-02-01', ($jahr + 1).'-07-31'],
            ] as [$bezeichnung, $start, $ende]) {
                $id = DB::table('semester')->where('bezeichnung', $bezeichnung)->value('semester_id')
                    ?? DB::table('semester')->insertGetId(['bezeichnung' => $bezeichnung, 'start_datum' => $start, 'end_datum' => $ende, 'sortierung' => $sort]);
                $out->push((object) ['semester_id' => (int) $id, 'bezeichnung' => $bezeichnung, 'start_datum' => $start, 'end_datum' => $ende]);
                $sort++;
            }
        }

        return $out;
    }

    /** @return array<string, list<array{fach_id: int, name: string}>> */
    private function seedeFaecher(): array
    {
        $out = [];
        foreach (self::FAECHER as $track => $liste) {
            foreach ($liste as $name => $kurz) {
                $id = DB::table('faecher')->where('track_typ', $track)->where('name', $name)->value('fach_id')
                    ?? DB::table('faecher')->insertGetId(['track_typ' => $track, 'kategorie_id' => $this->kategorien[$track], 'name' => $name, 'kurzname' => $kurz, 'aktiv' => 1]);
                $out[$track][] = ['fach_id' => (int) $id, 'name' => $name];
            }
        }

        return $out;
    }

    /** @return array<string, array{lehrberuf_id: int, module: list<array{modul_id: int, semester: int, kategorie: string}>}> */
    private function seedeLehrberufeUndModule(): array
    {
        $out = [];
        foreach (self::LEHRBERUFE as $kuerzel => $lb) {
            $lehrberufId = (int) Lehrberuf::query()->firstOrCreate(['kuerzel' => $kuerzel], ['name' => $lb['name'], 'aktiv' => true])->lehrberuf_id;
            $module = [];

            foreach (self::SCHUL_THEMEN as $i => $thema) {
                $module[] = $this->modul($lehrberufId, 'D'.($lb['basis'] + $i + 1), $thema, (int) ceil(($i + 1) * 8 / 12), 'FACH');
            }
            foreach (self::UEK_THEMEN as $i => $thema) {
                $module[] = $this->modul($lehrberufId, 'U'.($lb['basis'] + $i + 1), $thema, [1, 2, 3, 4, 6][$i], 'UEK');
            }

            $out[$kuerzel] = ['lehrberuf_id' => $lehrberufId, 'module' => $module];
        }

        return $out;
    }

    private function modul(int $lehrberufId, string $nummer, string $thema, int $semester, string $kategorie): array
    {
        $id = DB::table('module')->where('modul_nummer', $nummer)->value('modul_id')
            ?? DB::table('module')->insertGetId(['modul_nummer' => $nummer, 'titel' => $thema, 'ziel_gewicht_summe_default' => 100.00, 'aktiv' => 1]);

        DB::table('lehrberuf_module')->updateOrInsert(
            ['lehrberuf_id' => $lehrberufId, 'modul_id' => $id],
            ['pflicht' => true, 'empfohlenes_lehrsemester_nr' => $semester, 'aktiv' => 1, 'kategorie_id' => $this->kategorien[$kategorie]]
        );

        return ['modul_id' => (int) $id, 'semester' => $semester, 'kategorie' => $kategorie];
    }

    private function seedePersonen(array $berufe): array
    {
        $benutzer = static function (array $paar): array {
            [$vorname, $nachname] = $paar;
            $slug = strtolower($vorname).'.'.strtolower($nachname);

            return ['vorname' => $vorname, 'nachname' => $nachname, 'benutzername' => $slug, 'email' => $slug.'@demo.example', 'passwort_hash' => self::DEMO_PASSWORT];
        };

        $admin = User::factory()->admin()->create($benutzer(self::NAMEN[0]));
        $bbs = [];
        for ($i = 1; $i <= 3; $i++) {
            $bbs[] = User::factory()->berufsbildner()->create($benutzer(self::NAMEN[$i]));
        }

        $lernende = [];
        foreach (self::LERNENDE_KONFIG as $i => $cfg) {
            $lehrbeginn = "{$cfg['jahr']}-08-01";
            $lehrende = ($cfg['jahr'] + 4).'-07-31';
            $user = User::factory()->lernender([
                'lehrberuf_id' => $berufe[$cfg['beruf']]['lehrberuf_id'],
                'lehrbeginn' => $lehrbeginn,
                'lehrende' => $lehrende,
                'klasse_schule' => $cfg['klasse'],
                'klasse_bms' => $cfg['track'] === 'BMS' ? 'BM1-'.substr((string) $cfg['jahr'], 2) : null,
            ])->create($benutzer(self::NAMEN[$i + 4]));

            // aktive Betreuung + eine abgelaufene (Wechsel nach einem Semester)
            $aktiv = $bbs[$i % 3];
            $vorher = $bbs[($i + 1) % 3];
            $wechsel = Carbon::parse($lehrbeginn)->addDays(182);
            Betreuung::create(['berufsbildner_id' => $vorher->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id,
                'gueltig_von' => $lehrbeginn, 'gueltig_bis' => $wechsel->toDateString()]);
            Betreuung::create(['berufsbildner_id' => $aktiv->berufsbildner->berufsbildner_id, 'lernender_id' => $user->lernender->lernender_id,
                'gueltig_von' => $wechsel->copy()->addDay()->toDateString(), 'gueltig_bis' => null]);

            $lernende[] = ['user' => $user, 'lernender' => $user->lernender, 'bb' => $aktiv, 'lehrbeginn' => $lehrbeginn, 'lehrende' => $lehrende] + $cfg;
        }

        return ['admin' => $admin, 'bbs' => $bbs, 'lernende' => $lernende];
    }

    private function seedeLernenden(array $l, int $index, Collection $alleSemester, array $faecher, array $berufe, int &$kommentar): void
    {
        $lernenderId = (int) $l['lernender']->lernender_id;
        $benutzerId = (int) $l['user']->benutzer_id;
        $ref = Carbon::parse(self::REFERENZDATUM);
        $ende = min(self::REFERENZDATUM, $l['lehrende']);
        $kappung = $l['muster'] === 'inaktiv' ? $ref->copy()->subDays(60)->toDateString() : $ende;
        $laufend = $l['lehrende'] > self::REFERENZDATUM;

        $semester = $alleSemester->filter(fn ($s) => $s->start_datum >= $l['lehrbeginn'] && $s->start_datum <= $ende)->values();
        $aktuellesIndex = $semester->count() - 1;

        DB::table('lernender_tracks')->insert([
            'lernender_id' => $lernenderId, 'track_typ' => $l['track'], 'start_datum' => $l['lehrbeginn'],
            'end_datum' => $laufend ? null : $l['lehrende'],
            'start_semester_id' => $semester->first()->semester_id,
            'end_semester_id' => $laufend ? null : $semester->last()->semester_id,
        ]);

        $noten = [];
        $seed = $lernenderId * 7919;

        // Module: abgeschlossene mit vollen Gewichten, im laufenden Semester nur teilweise erfasst
        foreach ($berufe[$l['beruf']]['module'] as $mi => $m) {
            if ($m['semester'] > $semester->count()) {
                continue;
            }
            $sem = $semester[$m['semester'] - 1];
            $imLaufenden = $laufend && $aktuellesIndex === $m['semester'] - 1;
            $gewichte = $m['kategorie'] === 'UEK' ? [100] : [[100], [50, 50], [30, 30, 40]][$mi % 3];
            if ($imLaufenden) {
                $gewichte = [40];
            }
            $belegung = null;

            foreach ($gewichte as $gi => $gewicht) {
                $datum = $this->datumIm($sem, 30 + $gi * 45 + ($mi % 4) * 6, $kappung);
                if ($datum === null) {
                    continue;
                }
                $belegung ??= DB::table('modul_belegungen')->insertGetId(['lernender_id' => $lernenderId, 'modul_id' => $m['modul_id'], 'start_datum' => $datum, 'end_datum' => null]);
                $wert = $this->wert($seed + $m['modul_id'] * 31 + $gi, $l['muster'], $m['semester'], $semester->count(), false, $m['kategorie'] === 'UEK' ? 0.2 : 0.0);
                $noten[] = $this->note($lernenderId, $benutzerId, $m['kategorie'], (int) $sem->semester_id, null, (int) $belegung, $datum, $wert, $gewicht, $gi === 0 ? 'LB'.($gi + 1) : null);
            }

            if ($imLaufenden) {
                $this->plane($lernenderId, null, $m['modul_id'], $ref->copy()->addDays(12 + $mi)->toDateString(), 60, 'Schlussprüfung');
            }
        }

        // Fächer: 2–3 Prüfungen pro Semester
        foreach ($semester as $si => $sem) {
            foreach ($faecher[$l['track']] as $fi => $f) {
                $anzahl = 2 + (($seed + $fi + $si) % 2);
                $gewichte = $anzahl === 2 ? [100, 100] : [100, 50, 50];
                foreach ($gewichte as $gi => $gewicht) {
                    $datum = $this->datumIm($sem, 25 + $gi * 50 + $fi * 4, $kappung);
                    if ($datum === null) {
                        continue;
                    }
                    $dip = $l['muster'] === 'einbruch' && $f['name'] === 'Mathematik' && $si >= $semester->count() - 2;
                    $wert = $this->wert($seed + $f['fach_id'] * 17 + $si * 5 + $gi, $l['muster'], $si + 1, $semester->count(), $dip);
                    $noten[] = $this->note($lernenderId, $benutzerId, $l['track'], (int) $sem->semester_id, $f['fach_id'], null, $datum, $wert, $gewicht, null);
                }
                if ($laufend && $si === $aktuellesIndex && $fi < 3) {
                    $this->plane($lernenderId, $f['fach_id'], null, $ref->copy()->addDays(6 + $fi * 9)->toDateString(), 100, 'Test');
                }
            }
        }

        if ($l['muster'] === 'inaktiv') {
            $this->plane($lernenderId, $faecher[$l['track']][0]['fach_id'], null, $ref->copy()->subDays(9)->toDateString(), 100, 'Test');
        }

        $this->ziele($lernenderId, $l, $faecher);

        foreach ($noten as $ni => $note) {
            if ($ni % 3 === 0) {
                NotenGesehen::create(['note_id' => $note->note_id, 'viewer_benutzer_id' => $l['bb']->benutzer_id,
                    'gesehen_am' => Carbon::parse($note->pruefungsdatum)->addDays(3)]);
            }
            if ($ni % 7 === 0) {
                NotenKommentar::create(['note_id' => $note->note_id, 'autor_benutzer_id' => $l['bb']->benutzer_id,
                    'kommentar_text' => self::KOMMENTARE[$kommentar++ % count(self::KOMMENTARE)]]);
            }
        }

        if ($index % 4 === 2 && $noten !== []) {
            $noten[0]->delete();
        }
    }

    private function ziele(int $lernenderId, array $l, array $faecher): void
    {
        $mathe = collect($faecher['BMS'])->firstWhere('name', 'Mathematik')['fach_id'];
        $ziele = match ($l['muster']) {
            'stetig_besser' => [['ebene' => 'gesamt', 'zielwert' => 5.0]],
            'einbruch' => [['ebene' => 'fach', 'fach_id' => $mathe, 'zielwert' => 4.5], ['ebene' => 'kategorie', 'kategorie_id' => $this->kategorien['BMS'], 'zielwert' => 4.5]],
            'knapp' => [['ebene' => 'gesamt', 'zielwert' => 4.5]],
            default => [],
        };
        foreach ($ziele as $z) {
            DB::table('ziele')->insert(['lernender_id' => $lernenderId, 'kategorie_id' => null, 'fach_id' => null, 'modul_id' => null, ...$z]);
        }
    }

    private function plane(int $lernenderId, ?int $fachId, ?int $modulId, string $datum, float $gewicht, string $titel): void
    {
        DB::table('pruefungen')->insert(['lernender_id' => $lernenderId, 'fach_id' => $fachId, 'modul_id' => $modulId,
            'datum' => $datum, 'gewichtung_prozent' => $gewicht, 'titel' => $titel]);
    }

    private function datumIm(object $semester, int $offsetTage, string $kappung): ?string
    {
        $d = Carbon::parse($semester->start_datum)->addDays($offsetTage);
        $ende = Carbon::parse($semester->end_datum)->subDays(10);
        if ($d->gt($ende)) {
            $d = $ende;
        }

        return $d->toDateString() <= $kappung ? $d->toDateString() : null;
    }

    /** Pseudozufall um ein Zentrum je Muster, gerundet auf Viertelnoten. */
    private function wert(int $seed, string $muster, int $semesterNr, int $anzahl, bool $dip, float $bonus = 0.0): float
    {
        [$zentrum, $streuung] = match ($muster) {
            'stetig_besser' => [min(5.6, 3.9 + 0.28 * ($semesterNr - 1)), 0.35],
            'einbruch' => $dip ? [3.1, 0.3] : [4.8, 0.35],
            'stabil_gut' => [5.25, 0.3],
            'knapp' => [3.95, 0.45],
            'schwankend' => [4.4, 0.9],
            default => [4.6, 0.5],
        };
        $x = sin($seed * 12.9898) * 43758.5453;
        $anteil = $x - floor($x);
        $wert = max(1.0, min(6.0, $zentrum + $bonus + ($anteil - 0.5) * 2 * $streuung));

        return round($wert * 4) / 4;
    }

    private function note(int $lernenderId, int $benutzerId, string $kategorie, int $semesterId, ?int $fachId, ?int $belegungId, string $datum, float $wert, float $gewicht, ?string $titel): Note
    {
        return Note::create([
            'lernender_id' => $lernenderId, 'kategorie_id' => $this->kategorien[$kategorie], 'semester_id' => $semesterId,
            'fach_id' => $fachId, 'modul_belegung_id' => $belegungId, 'titel' => $titel, 'pruefungsdatum' => $datum,
            'note_wert' => $wert, 'gewichtung_prozent' => $gewicht, 'erfasst_von_benutzer_id' => $benutzerId, 'aktualisiert_von_benutzer_id' => null,
        ]);
    }
}
