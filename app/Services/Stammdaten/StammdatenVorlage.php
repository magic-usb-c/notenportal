<?php

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Notenbaum\Baum;
use App\Services\Auswertung\Notenbaum\BaumVorlage;
use App\Support\Lehrsemester;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stammdaten-Vorlagen für die Einrichtung: welche Lehrberufe und Fächer zur Auswahl stehen, was
 * vorausgewählt ist und welche Notenbäume dazugehören. Regionale Realität (Italienisch in Graubünden,
 * Sport als Stufe) steht in Dateien unter resources/vorlagen/stammdaten, nicht im Code.
 *
 * Fächer tragen einen Schlüssel «TRACK:KÜRZEL» (ohne Track «BERUF:KÜRZEL»); Fächer ohne Track werden
 * den gewählten Lehrberufen freigegeben, Fächer mit Track gelten über den Track.
 */
final class StammdatenVorlage
{
    public const string FORMAT = 'notenportal-stammdaten';

    public const int VERSION = 1;

    public const string OHNE_TRACK = 'BERUF';

    public static function verzeichnis(): string
    {
        return resource_path('vorlagen/stammdaten');
    }

    /** @return array<string, array<string, mixed>> geprüfte Vorlagen nach Schlüssel, die Standardvorlage zuerst */
    public static function mitgeliefert(): array
    {
        $out = [];
        foreach (glob(self::verzeichnis().'/*.json') ?: [] as $datei) {
            $d = json_decode((string) file_get_contents($datei), true);
            if (is_array($d) && self::pruefen($d) === []) {
                $out[basename($datei, '.json')] = $d;
            }
        }
        uksort($out, fn ($a, $b) => [! ($out[$a]['standard'] ?? false), $a] <=> [! ($out[$b]['standard'] ?? false), $b]);

        return $out;
    }

    public static function standard(): ?string
    {
        return array_key_first(self::mitgeliefert());
    }

    /** @return array<string, mixed> */
    public static function laden(?string $schluessel): array
    {
        $liste = self::mitgeliefert();
        $schluessel ??= array_key_first($liste);

        return $liste[$schluessel] ?? throw new RuntimeException(__('Unbekannte Vorlage «:name».', ['name' => (string) $schluessel]));
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<string> Fehler; leer = gültig
     */
    public static function pruefen(array $d): array
    {
        $fehler = [];
        if (($d['format'] ?? null) !== self::FORMAT || (int) ($d['version'] ?? 0) !== self::VERSION) {
            return ['format'];
        }
        if (trim((string) ($d['name'] ?? '')) === '') {
            $fehler[] = 'name';
        }
        $kuerzel = [];
        foreach ($d['lehrberufe'] ?? [] as $i => $l) {
            $k = (string) ($l['kuerzel'] ?? '');
            if (! preg_match('/^[A-Z0-9]{1,10}$/', $k) || trim((string) ($l['name'] ?? '')) === '' || isset($kuerzel[$k])) {
                $fehler[] = "lehrberufe.$i";
            }
            $kuerzel[$k] = true;
        }
        $schluessel = [];
        foreach ($d['faecher'] ?? [] as $i => $f) {
            $s = self::fachSchluessel($f);
            if (! preg_match('/^[A-Z0-9]{1,10}$/', (string) ($f['kurzname'] ?? '')) || trim((string) ($f['name'] ?? '')) === ''
                || ! in_array($f['track'] ?? null, [null, 'BMS', 'ABU'], true) || isset($schluessel[$s])
                || ! in_array($f['skala'] ?? 'note', ['note', 'stufe'], true)) {
                $fehler[] = "faecher.$i";
            }
            $schluessel[$s] = true;
        }

        return $fehler;
    }

    /** @param  array<string, mixed>  $f */
    public static function fachSchluessel(array $f): string
    {
        return ($f['track'] ?? null ?: self::OHNE_TRACK).':'.$f['kurzname'];
    }

    /**
     * Gewählte Lehrberufe und Fächer anlegen, fehlende Notenbäume laden. Bestehendes bleibt unangetastet:
     * vorhandene Lehrberufe (Kürzel oder Name) und Fächer (Name im selben Track) werden nicht doppelt angelegt.
     *
     * @param  array<string, mixed>  $vorlage
     * @param  list<string>  $berufe  Kürzel aus der Vorlage
     * @param  array<string, string>  $eigene  Kürzel => Name zusätzlicher Lehrberufe
     * @param  list<string>  $faecher  Fachschlüssel aus der Vorlage
     * @return array{berufe: int, faecher: int, baeume: int}
     */
    public function anwenden(array $vorlage, array $berufe, array $eigene, array $faecher, bool $baeume = true): array
    {
        $katalogBerufe = collect($vorlage['lehrberufe'] ?? [])->keyBy('kuerzel');
        $katalogFaecher = collect($vorlage['faecher'] ?? [])->keyBy(fn ($f) => self::fachSchluessel($f));
        $kategorien = DB::table('kategorien')->pluck('kategorie_id', 'code')->map(fn ($v) => (int) $v)->all();

        $ergebnis = DB::transaction(function () use ($berufe, $eigene, $faecher, $katalogBerufe, $katalogFaecher, $kategorien) {
            $neuBerufe = 0;
            $lehrberufIds = [];
            $wunsch = [];
            foreach ($berufe as $k) {
                if (isset($katalogBerufe[$k])) {
                    $wunsch[$k] = (string) $katalogBerufe[$k]['name'];
                }
            }
            foreach ($eigene as $k => $name) {
                $wunsch[$k] = $name;
            }
            foreach ($wunsch as $kuerzel => $name) {
                $id = DB::table('lehrberufe')->where('kuerzel', $kuerzel)->orWhere('name', $name)->value('lehrberuf_id');
                if ($id === null) {
                    $id = DB::table('lehrberufe')->insertGetId(['kuerzel' => $kuerzel, 'name' => $name, 'aktiv' => 1]);
                    $neuBerufe++;
                }
                $lehrberufIds[$kuerzel] = (int) $id;
            }
            // Schon vorhandene Lehrberufe der Vorlage bekommen neue Fächer und fehlende Bäume ebenfalls
            foreach (DB::table('lehrberufe')->whereIn('kuerzel', $katalogBerufe->keys()->all())->pluck('lehrberuf_id', 'kuerzel') as $k => $id) {
                $lehrberufIds[$k] ??= (int) $id;
            }

            $neuFaecher = 0;
            foreach ($faecher as $schluessel) {
                $f = $katalogFaecher[$schluessel] ?? null;
                if ($f === null) {
                    continue;
                }
                $track = $f['track'] ?? null;
                $kategorie = $kategorien[$f['kategorie'] ?? ($track ?? 'FACH')] ?? null;
                if ($kategorie === null) {
                    continue;
                }
                $fachId = DB::table('faecher')->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $f['name'])])
                    ->where(fn ($q) => $track === null ? $q->whereNull('track_typ') : $q->where('track_typ', $track))
                    ->value('fach_id');
                if ($fachId === null) {
                    $fachId = DB::table('faecher')->insertGetId([
                        'name' => $f['name'], 'kurzname' => $this->freiesKuerzel((string) $f['kurzname'], $track),
                        'kategorie_id' => $kategorie, 'track_typ' => $track,
                        'skala' => ($f['skala'] ?? 'note') === 'stufe' ? 'stufe' : 'note', 'zaehlt' => (bool) ($f['zaehlt'] ?? true),
                        'aktiv' => 1, 'erstellt_am' => now(), 'aktualisiert_am' => now(),
                    ]);
                    $neuFaecher++;
                }
                if ($track === null) {
                    foreach ($lehrberufIds as $lehrberufId) {
                        DB::table('lehrberuf_faecher')->insertOrIgnore(['lehrberuf_id' => $lehrberufId, 'fach_id' => (int) $fachId]);
                    }
                }
            }

            return ['berufe' => $neuBerufe, 'faecher' => $neuFaecher, 'ids' => $lehrberufIds];
        });

        $geladen = $baeume ? $this->baeumeLaden($vorlage, $ergebnis['ids'], $faecher) : 0;
        Konfiguration::vergessen();
        Lehrsemester::vergessen();

        return ['berufe' => $ergebnis['berufe'], 'faecher' => $ergebnis['faecher'], 'baeume' => $geladen];
    }

    /**
     * Notenbaum je gewähltem Lehrberuf und je Bildungsgang, sofern dort noch keiner aktiv ist.
     *
     * @param  array<string, mixed>  $vorlage
     * @param  array<string, int>  $lehrberufIds
     * @param  list<string>  $faecher
     */
    private function baeumeLaden(array $vorlage, array $lehrberufIds, array $faecher): int
    {
        $baumVorlagen = app(BaumVorlage::class);
        $bekannt = BaumVorlage::mitgeliefert();
        $anzahl = 0;

        foreach ($vorlage['lehrberufe'] ?? [] as $l) {
            $schluessel = $l['notenbaum'] ?? null;
            $id = $lehrberufIds[$l['kuerzel']] ?? null;
            if ($schluessel === null || $id === null || ! isset($bekannt[$schluessel])
                || DB::table('notenbaeume')->where('aktiv', true)->where('bezug', Baum::LEHRBERUF)->where('lehrberuf_id', $id)->exists()) {
                continue;
            }
            $baumVorlagen->importieren(BaumVorlage::laden($schluessel), $id, $schluessel);
            $anzahl++;
        }

        $tracks = collect($faecher)->map(fn ($s) => explode(':', $s)[0])->unique()->all();
        foreach ($vorlage['notenbaeume'] ?? [] as $b) {
            $schluessel = $b['vorlage'] ?? null;
            $track = $b['track'] ?? null;
            if ($schluessel === null || ! isset($bekannt[$schluessel]) || ! in_array($track, $tracks, true)
                || DB::table('notenbaeume')->where('aktiv', true)->where('bezug', Baum::BILDUNGSGANG)->where('track_typ', $track)->exists()) {
                continue;
            }
            $baumVorlagen->importieren(BaumVorlage::laden($schluessel), null, $schluessel);
            $anzahl++;
        }

        return $anzahl;
    }

    private function freiesKuerzel(string $wunsch, ?string $track): string
    {
        $kandidat = $wunsch;
        for ($i = 2; DB::table('faecher')->where('kurzname', $kandidat)
            ->where(fn ($q) => $track === null ? $q->whereNull('track_typ') : $q->where('track_typ', $track))->exists(); $i++) {
            $kandidat = $wunsch.$i;
        }

        return $kandidat;
    }
}
