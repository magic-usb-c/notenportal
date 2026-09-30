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

    /** Ablage der Vorlagen; `notenportal.stammdaten_verzeichnis` ist für Tests und abweichende Installationen überschreibbar. */
    public static function verzeichnis(): string
    {
        return (string) config('notenportal.stammdaten_verzeichnis', resource_path('vorlagen/stammdaten'));
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
     * Prüft Format, Typen und Verweise auf Baumvorlagen der ganzen Datei; Fehler werden gemeldet, nie geworfen –
     * eine kaputte Vorlage fehlt dann nur in der Auswahl.
     *
     * @param  array<string, mixed>  $d
     * @return list<string> Fehler; leer = gültig
     */
    public static function pruefen(array $d): array
    {
        if (($d['format'] ?? null) !== self::FORMAT || ($d['version'] ?? null) !== self::VERSION) {
            return ['format'];
        }
        $text = fn (mixed $v, int $max = 200): bool => is_string($v) && trim($v) !== '' && mb_strlen($v) <= $max;
        $optText = fn (mixed $v): bool => $v === null || is_string($v);
        $optBool = fn (mixed $v): bool => $v === null || is_bool($v);
        $kurz = fn (mixed $v): bool => is_string($v) && preg_match('/^[A-Z0-9]{1,10}$/', $v) === 1;

        $fehler = [];
        if (! $text($d['name'] ?? null)) {
            $fehler[] = 'name';
        }
        if (! $optText($d['beschreibung'] ?? null)) {
            $fehler[] = 'beschreibung';
        }
        if (! $optBool($d['standard'] ?? null)) {
            $fehler[] = 'standard';
        }
        foreach (['lehrberufe', 'faecher', 'notenbaeume'] as $liste) {
            if (isset($d[$liste]) && (! is_array($d[$liste]) || ! array_is_list($d[$liste]))) {
                $fehler[] = $liste;
                $d[$liste] = [];
            }
        }

        // Verweise auf Baumvorlagen samt Bezug: ein Tippfehler liess den Baum beim Anwenden sonst still weg,
        // ein Bildungsgang-Baum unter einem Lehrberuf (oder umgekehrt) landete falsch zugeordnet.
        $baeume = null;
        $baum = function (mixed $v, string $bezug) use (&$baeume): bool {
            $baeume ??= BaumVorlage::mitgeliefert();

            return is_string($v) && ($baeume[$v]['bezug'] ?? null) === $bezug;
        };

        $kuerzel = [];
        foreach ($d['lehrberufe'] ?? [] as $i => $l) {
            if (! is_array($l) || ! $kurz($l['kuerzel'] ?? null) || ! $text($l['name'] ?? null) || isset($kuerzel[$l['kuerzel']])
                || ! $optText($l['notenbaum'] ?? null) || ! $optBool($l['vorauswahl'] ?? null)
                || (isset($l['notenbaum']) && ! $baum($l['notenbaum'], Baum::LEHRBERUF))) {
                $fehler[] = "lehrberufe.$i";
            }
            if (is_array($l) && is_string($l['kuerzel'] ?? null)) {
                $kuerzel[$l['kuerzel']] = true;
            }
        }

        $schluessel = [];
        foreach ($d['faecher'] ?? [] as $i => $f) {
            $ok = is_array($f) && $kurz($f['kurzname'] ?? null) && $text($f['name'] ?? null)
                && in_array($f['track'] ?? null, [null, 'BMS', 'ABU'], true)
                && in_array($f['skala'] ?? 'note', ['note', 'stufe'], true)
                && $optText($f['kategorie'] ?? null) && $optBool($f['zaehlt'] ?? null) && $optBool($f['vorauswahl'] ?? null);
            if ($ok && isset($schluessel[self::fachSchluessel($f)])) {
                $ok = false;
            }
            if (! $ok) {
                $fehler[] = "faecher.$i";
            } else {
                $schluessel[self::fachSchluessel($f)] = true;
            }
        }

        foreach ($d['notenbaeume'] ?? [] as $i => $b) {
            if (! is_array($b) || ! $text($b['vorlage'] ?? null) || ! in_array($b['track'] ?? null, ['BMS', 'ABU'], true)
                || ! $baum($b['vorlage'], Baum::BILDUNGSGANG)) {
                $fehler[] = "notenbaeume.$i";
            }
        }

        return $fehler;
    }

    /** @param  array<string, mixed>  $f */
    public static function fachSchluessel(array $f): string
    {
        return ($f['track'] ?? null ?: self::OHNE_TRACK).':'.$f['kurzname'];
    }

    /**
     * Welche Lehrberufe der Vorlage es in der Datenbank schon gibt: Wiedererkennung über Kürzel ODER Name,
     * ohne Gross-/Kleinschreibung. Formular und Anlegen gehen beide von dieser Antwort aus.
     *
     * @param  array<string, mixed>  $vorlage
     * @return array<string, int> Kürzel in der Vorlage => lehrberuf_id
     */
    public static function vorhandeneBerufe(array $vorlage): array
    {
        $out = [];
        foreach ($vorlage['lehrberufe'] ?? [] as $l) {
            $id = self::lehrberufId((string) $l['kuerzel'], (string) $l['name']);
            if ($id !== null) {
                $out[(string) $l['kuerzel']] = $id;
            }
        }

        return $out;
    }

    /**
     * Welche Fächer der Vorlage es schon gibt (Name im selben Track, ohne Gross-/Kleinschreibung). Die Zeile
     * trägt die tatsächlichen Werte; was dort steht, gilt, nicht die Angabe der Vorlage.
     *
     * @param  array<string, mixed>  $vorlage
     * @return array<string, object{fach_id: int, skala: string, zaehlt: int|bool}> Fachschlüssel der Vorlage => Fach
     */
    public static function vorhandeneFaecher(array $vorlage): array
    {
        $index = self::fachIndex();
        $out = [];
        foreach ($vorlage['faecher'] ?? [] as $f) {
            $da = $index[self::fachName($f['track'] ?? null, (string) $f['name'])] ?? null;
            if ($da !== null) {
                $out[self::fachSchluessel($f)] = $da;
            }
        }

        return $out;
    }

    /**
     * Gewählte Lehrberufe und Fächer anlegen, fehlende Notenbäume laden – ganz oder gar nicht: scheitert etwas
     * (RuntimeException mit lesbarer Meldung), bleibt nichts zurück. Bestehendes bleibt unangetastet:
     * vorhandene Lehrberufe (Kürzel oder Name) und Fächer (Name im selben Track) werden weder doppelt angelegt
     * noch verändert. Neue Lehrberufe bekommen auch die schon vorhandenen Fächer ohne Track der Vorlage.
     *
     * @param  array<string, mixed>  $vorlage
     * @param  list<string>  $berufe  Kürzel aus der Vorlage
     * @param  array<string, string>  $eigene  Kürzel => Name zusätzlicher Lehrberufe
     * @param  list<string>  $faecher  Fachschlüssel aus der Vorlage
     * @return array{berufe: int, faecher: int, baeume: int}
     *
     * @throws RuntimeException wenn die Vorlage nicht zu den Stammdaten passt (unbekannte Kategorie, fehlerhafter Notenbaum, vergebenes Kürzel)
     */
    public function anwenden(array $vorlage, array $berufe, array $eigene, array $faecher, bool $baeume = true): array
    {
        try {
            return DB::transaction(function () use ($vorlage, $berufe, $eigene, $faecher, $baeume) {
                $kategorien = DB::table('kategorien')->pluck('kategorie_id', 'code')->map(fn ($v) => (int) $v)->all();

                [$lehrberufIds, $neueIds] = $this->berufeAnlegen($vorlage, $berufe, $eigene);
                [$neuFaecher, $vorhanden] = $this->faecherAnlegen($vorlage, $faecher, $lehrberufIds, $neueIds, $kategorien);
                $geladen = $baeume ? $this->baeumeLaden($vorlage, $lehrberufIds, $vorhanden) : 0;

                return ['berufe' => count($neueIds), 'faecher' => $neuFaecher, 'baeume' => $geladen];
            });
        } finally {
            Konfiguration::vergessen();
            Lehrsemester::vergessen();
        }
    }

    /**
     * @param  array<string, mixed>  $vorlage
     * @param  list<string>  $berufe
     * @param  array<string, string>  $eigene
     * @return array{0: array<string, int>, 1: list<int>} Kürzel => id aller Lehrberufe, die Fächer und Bäume bekommen; ids der neu angelegten
     */
    private function berufeAnlegen(array $vorlage, array $berufe, array $eigene): array
    {
        $katalog = collect($vorlage['lehrberufe'] ?? [])->keyBy('kuerzel');
        // Schon vorhandene Lehrberufe der Vorlage bekommen neue Fächer und fehlende Bäume ebenfalls
        $ids = self::vorhandeneBerufe($vorlage);
        $neue = [];

        $wunsch = [];
        foreach ($berufe as $k) {
            if (isset($katalog[$k])) {
                $wunsch[(string) $k] = (string) $katalog[$k]['name'];
            }
        }
        foreach ($eigene as $k => $name) {
            // Ein vergebenes Kürzel unter fremdem Namen würde unten stillschweigend auf den vorhandenen Lehrberuf fallen
            if (self::kuerzelFremdvergeben((string) $k, $name)) {
                throw new RuntimeException(__('Kürzel «:kuerzel» gehört zu einem anderen Lehrberuf.', ['kuerzel' => (string) $k]));
            }
            $wunsch[(string) $k] = $name;
        }
        foreach ($wunsch as $kuerzel => $name) {
            $kuerzel = (string) $kuerzel; // rein numerische Kürzel werden als Array-Schlüssel zu int
            $id = $ids[$kuerzel] ?? self::lehrberufId($kuerzel, $name);
            if ($id === null) {
                $id = (int) DB::table('lehrberufe')->insertGetId(['kuerzel' => $kuerzel, 'name' => $name, 'aktiv' => 1]);
                $neue[] = $id;
            }
            $ids[$kuerzel] = $id;
        }

        return [$ids, $neue];
    }

    /**
     * Fächer ohne Track gehen an die gewählten Lehrberufe; ein schon vorhandenes Fach, das nicht mitgeschickt
     * wurde (im Formular deaktiviert), nur an die neu angelegten Lehrberufe – bestehende Freigaben bleiben.
     *
     * @param  array<string, mixed>  $vorlage
     * @param  list<string>  $faecher
     * @param  array<string, int>  $lehrberufIds
     * @param  list<int>  $neueBerufe
     * @param  array<string, int>  $kategorien
     * @return array{0: int, 1: list<string>} neu angelegte Fächer; Schlüssel der Fächer, die gewählt oder schon vorhanden sind
     */
    private function faecherAnlegen(array $vorlage, array $faecher, array $lehrberufIds, array $neueBerufe, array $kategorien): array
    {
        $gewaehlt = array_flip($faecher);
        $index = self::fachIndex();
        $neu = 0;
        $vorhanden = [];

        foreach ($vorlage['faecher'] ?? [] as $f) {
            $schluessel = self::fachSchluessel($f);
            $track = $f['track'] ?? null;
            $name = self::fachName($track, (string) $f['name']);
            $da = $index[$name] ?? null;
            $gewollt = isset($gewaehlt[$schluessel]);
            if (! $gewollt && $da === null) {
                continue;
            }

            if ($da === null) {
                $code = (string) ($f['kategorie'] ?? ($track ?? 'FACH'));
                $kategorie = $kategorien[$code] ?? throw new RuntimeException(__('Fach «:name»: Kategorie «:code» gibt es nicht.', ['name' => (string) $f['name'], 'code' => $code]));
                $fachId = (int) DB::table('faecher')->insertGetId([
                    'name' => $f['name'], 'kurzname' => $this->freiesKuerzel((string) $f['kurzname'], $track),
                    'kategorie_id' => $kategorie, 'track_typ' => $track,
                    'skala' => ($f['skala'] ?? 'note') === 'stufe' ? 'stufe' : 'note', 'zaehlt' => (bool) ($f['zaehlt'] ?? true),
                    'aktiv' => 1, 'erstellt_am' => now(), 'aktualisiert_am' => now(),
                ]);
                $index[$name] = (object) ['fach_id' => $fachId];
                $neu++;
            } else {
                $fachId = (int) $da->fach_id;
            }
            $vorhanden[] = $schluessel;

            if ($track === null) {
                foreach ($gewollt ? $lehrberufIds : $neueBerufe as $lehrberufId) {
                    DB::table('lehrberuf_faecher')->insertOrIgnore(['lehrberuf_id' => $lehrberufId, 'fach_id' => $fachId]);
                }
            }
        }

        return [$neu, $vorhanden];
    }

    /**
     * Notenbaum je gewähltem Lehrberuf und je Bildungsgang, sofern dort noch keiner aktiv ist. Ein Bildungsgang
     * gilt als gewählt, wenn ein Fach seines Tracks gewählt oder schon vorhanden ist.
     *
     * @param  array<string, mixed>  $vorlage
     * @param  array<string, int>  $lehrberufIds
     * @param  list<string>  $faecher  Schlüssel der gewählten oder schon vorhandenen Fächer
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

    /**
     * Trägt schon ein Lehrberuf dieses Kürzel (ohne Gross-/Kleinschreibung) unter einem anderen Namen? Dann darf es
     * nicht für einen weiteren Lehrberuf gelten. Mit demselben Namen (ebenfalls ohne Gross-/Kleinschreibung) ist es derselbe.
     */
    public static function kuerzelFremdvergeben(string $kuerzel, string $name): bool
    {
        $kuerzel = mb_strtoupper(trim($kuerzel));
        $name = mb_strtolower(trim($name));

        return DB::table('lehrberufe')->get(['kuerzel', 'name'])->contains(
            fn ($l) => mb_strtoupper(trim((string) $l->kuerzel)) === $kuerzel && mb_strtolower(trim((string) $l->name)) !== $name
        );
    }

    /** Lehrberuf über Kürzel, sonst über Name; ohne Gross-/Kleinschreibung und ohne Leerraum am Rand. */
    private static function lehrberufId(string $kuerzel, string $name): ?int
    {
        $id = DB::table('lehrberufe')->whereRaw('LOWER(kuerzel) = ?', [mb_strtolower(trim($kuerzel))])->orderBy('lehrberuf_id')->value('lehrberuf_id')
            ?? DB::table('lehrberufe')->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->orderBy('lehrberuf_id')->value('lehrberuf_id');

        return $id === null ? null : (int) $id;
    }

    /** @return array<string, object> «TRACK:name» (klein) => vorhandenes Fach, bei Doppelten das mit der kleinsten ID */
    private static function fachIndex(): array
    {
        $index = [];
        foreach (DB::table('faecher')->orderBy('fach_id')->get(['fach_id', 'name', 'track_typ', 'skala', 'zaehlt']) as $f) {
            $index[self::fachName($f->track_typ, (string) $f->name)] ??= $f;
        }

        return $index;
    }

    private static function fachName(?string $track, string $name): string
    {
        return ($track ?: self::OHNE_TRACK).':'.mb_strtolower(trim($name));
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
