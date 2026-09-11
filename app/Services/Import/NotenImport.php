<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Note;
use App\Services\Noten\NoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Notenliste → Vorschau (Spalten erkennen, Fach/Modul zuordnen, Dubletten markieren) → Import über NoteService,
 * damit dieselben Regeln gelten wie beim Erfassen von Hand.
 */
final class NotenImport
{
    private const array KOPF = [
        'datum' => ['datum', 'date', 'prüfungsdatum', 'pruefungsdatum', 'am', 'tag'],
        'bezug' => ['fach', 'modul', 'fach/modul', 'fach / modul', 'bezug', 'kurs', 'gegenstand', 'lernfeld', 'fach oder modul'],
        'titel' => ['titel', 'thema', 'prüfung', 'pruefung', 'bezeichnung', 'beschreibung', 'test', 'notiz'],
        'note' => ['note', 'noten', 'bewertung', 'wert', 'grade', 'resultat'],
        'gewicht' => ['gewicht', 'gewichtung', 'gew', 'gew.', 'gew. %', 'gewichtung %', 'gewicht %', '%', 'faktor'],
    ];

    /**
     * Schulfächer mit gängigen Namen und Kürzeln (kompakt: klein, ohne Umlaute, Leer- und Satzzeichen).
     * «*» am Ende = Präfix. Ein Name, der hier bekannt ist, wird nie unscharf einem anderen Fach zugeordnet.
     */
    private const array SYNONYME = [
        ['deutsch', 'd', 'de', 'deu'],
        ['franzoesisch', 'f', 'fr', 'frz', 'franz', 'francais'],
        ['englisch', 'e', 'en', 'eng', 'english'],
        ['italienisch', 'it', 'ita', 'italiano'],
        ['mathematik', 'm', 'ma', 'mat', 'math', 'mathe'],
        ['naturwissenschaften', 'nw', 'nawi', 'naturwissenschaft'],
        ['wirtschaftundrecht', 'wr', 'wirtschaftrecht', 'wirtschaft'],
        ['geschichteundpolitik', 'gp', 'geschichtepolitik', 'geschichte'],
        ['technikundumwelt', 'tu', 'technikumwelt'],
        ['finanzundrechnungswesen', 'frw', 'rw', 'finanzrechnungswesen', 'rechnungswesen'],
        ['interdisziplinaer*', 'idaf', 'idpa'],
        ['allgemeinbildung', 'abu', 'allgemeinbildenderunterricht'],
        ['spracheundkommunikation', 'sk', 'sprachekommunikation'],
        ['gesellschaft', 'ges'],
        ['vertiefungsarbeit', 'va'],
        ['sport', 'spo', 'turnenundsport'],
        ['informatik', 'info', 'inf'],
    ];

    public function __construct(private readonly NoteService $noten) {}

    /**
     * @param  list<list<string>>  $tabelle
     * @return array{zeilen: list<array<string, mixed>>, erkannt: array<string, int>, format: ?string}
     */
    public function vorschau(array $tabelle, int $lernenderId): array
    {
        $format = count($tabelle[0] ?? []) === 1 && in_array($tabelle[0][0], [Schulnetz::AKTUELLE_NOTEN, Schulnetz::ZEUGNISNOTEN], true) ? $tabelle[0][0] : null;
        $katalog = $format ? $this->bevorzugt($this->katalog($lernenderId), false) : $this->katalog($lernenderId);
        [$kopf, $spalten] = $this->spalten($tabelle, $katalog);
        $vorhanden = $this->vorhandene($lernenderId);
        $fachErlaubt = $this->noten->fachErlaubtAm($lernenderId); // Track am Prüfungsdatum gültig

        $zeilen = [];
        foreach ($tabelle as $i => $zelle) {
            if ($i <= $kopf || count($zeilen) >= TabellenLeser::MAX_ZEILEN) {
                continue;
            }
            $roh = fn (string $s) => isset($spalten[$s]) ? trim((string) ($zelle[$spalten[$s]] ?? '')) : '';
            $datum = $this->datum($roh('datum'));
            $note = $this->note($roh('note'));
            $gewicht = $this->gewicht($roh('gewicht'));
            [$bezug, $sicher] = $this->bezug($roh('bezug') !== '' ? $roh('bezug') : $roh('titel'), $katalog);
            $trackFehlt = $datum !== null && $bezug !== null && str_starts_with($bezug, 'fach:')
                && ! $fachErlaubt($datum, (int) substr($bezug, 5));

            // Notenzeile = Note vorhanden oder Datum mit erkanntem Fach/Modul; Titel-, Kopf- und Fusszeilen fallen weg
            if ($note === null && ($datum === null || $bezug === null)) {
                continue;
            }

            $meldung = match (true) {
                $datum === null => $roh('datum') === '' ? 'Datum fehlt' : 'Datum nicht erkannt',
                $note === null => $roh('note') === '' ? 'Note fehlt' : 'Note ungültig',
                $gewicht === false => 'Gewicht ungültig',
                $bezug === null => 'Fach/Modul nicht erkannt',
                isset($vorhanden[$bezug.'|'.$datum.'|'.number_format($note, 2)]) => 'bereits erfasst',
                $trackFehlt => __('Track am Datum nicht aktiv'),
                ! $sicher => 'Zuordnung prüfen',
                default => null,
            };
            $status = match ($meldung) {
                null => 'ok',
                'bereits erfasst' => 'doppelt',
                'Zuordnung prüfen' => 'pruefen',
                default => 'fehler',
            };

            $zeilen[] = [
                'nr' => $i + 1,
                'datum' => $datum,
                'bezug' => $bezug,
                'bezug_roh' => $roh('bezug'),
                'titel' => mb_substr($roh('titel'), 0, 150),
                'note' => $note,
                'gewicht' => $gewicht === false || $gewicht === null ? 100 : $gewicht,
                'status' => $status,
                'meldung' => $meldung,
                'uebernehmen' => in_array($status, ['ok', 'pruefen'], true),
            ];
        }

        return ['zeilen' => $zeilen, 'erkannt' => $format ? [] : $spalten, 'format' => $format];
    }

    /**
     * @param  list<array<string, mixed>>  $zeilen  bearbeitete Vorschau (nur markierte werden übernommen)
     * @return array{neu: int, fehler: list<string>}
     */
    public function importieren(array $zeilen, int $lernenderId, int $benutzerId): array
    {
        $neu = 0;
        $fehler = [];
        DB::transaction(function () use ($zeilen, $lernenderId, $benutzerId, &$neu, &$fehler) {
            foreach ($zeilen as $z) {
                if (empty($z['uebernehmen'])) {
                    continue;
                }
                $nr = (int) ($z['nr'] ?? 0);
                $pruefung = Validator::make($z, [
                    'datum' => ['required', 'date'],
                    'bezug' => ['required', 'regex:/^(fach|modul):\d+$/'],
                    'titel' => ['nullable', 'string', 'max:150'],
                    'note' => ['required', 'numeric', 'min:1', 'max:6', 'multiple_of:0.05'],
                    'gewicht' => ['nullable', 'numeric', 'min:0', 'max:100'],
                ], [], ['datum' => 'Datum', 'bezug' => 'Fach/Modul', 'note' => 'Note', 'gewicht' => 'Gewicht']);
                if ($pruefung->fails()) {
                    $fehler[] = 'Zeile '.$nr.': '.$pruefung->errors()->first();

                    continue;
                }
                [$typ, $id] = explode(':', (string) $z['bezug']);
                try {
                    $daten = $this->noten->normalizeForSave([
                        'typ' => $typ,
                        'fach_id' => $typ === 'fach' ? (int) $id : null,
                        'modul_id' => $typ === 'modul' ? (int) $id : null,
                        'titel' => filled($z['titel'] ?? null) ? (string) $z['titel'] : null,
                        'pruefungsdatum' => (string) $z['datum'],
                        'note_wert' => (float) $z['note'],
                        'gewichtung_prozent' => $z['gewicht'] ?? 100,
                    ], $lernenderId);
                } catch (ValidationException $e) {
                    $fehler[] = 'Zeile '.$nr.': '.collect($e->errors())->flatten()->first();

                    continue;
                }

                Note::create([
                    'lernender_id' => $lernenderId,
                    ...array_intersect_key($daten, array_flip(['kategorie_id', 'semester_id', 'fach_id', 'modul_belegung_id', 'titel', 'pruefungsdatum', 'note_wert', 'gewichtung_prozent'])),
                    'erfasst_von_benutzer_id' => $benutzerId,
                ]);
                $neu++;
            }
        });

        return ['neu' => $neu, 'fehler' => $fehler];
    }

    /** CSV-Vorlage mit Kopfzeile und je einem Beispiel pro Fach/Modul-Art. */
    public function vorlage(int $lernenderId): string
    {
        $beispiele = collect($this->katalog($lernenderId))->take(2)->map(fn ($k) => $k['label'])->all();
        $zeilen = [['Datum', 'Fach/Modul', 'Titel', 'Note', 'Gewicht']];
        foreach ($beispiele as $i => $label) {
            $zeilen[] = [now()->subDays(7 * ($i + 1))->format('d.m.Y'), $label, 'Prüfung '.($i + 1), $i ? '5.0' : '4.5', '100'];
        }

        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn ($z) => implode(';', $z), $zeilen))."\r\n";
    }

    public function datum(string $s): ?string
    {
        $s = trim(preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $s) ?? '');
        if ($s === '') {
            return null;
        }
        if (is_numeric($s) && (float) $s > 20000 && (float) $s < 80000) {
            return Date::excelToDateTimeObject((float) $s)->format('Y-m-d');
        }
        foreach (['d.m.Y', 'j.n.Y', 'd.m.y', 'j.n.y', 'Y-m-d', 'd/m/Y', 'j/n/Y'] as $format) {
            $d = \DateTime::createFromFormat('!'.$format, $s);
            if ($d && $d->format($format) === $s) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    public function note(string $s): ?float
    {
        $s = str_replace(',', '.', trim($s));
        if (! is_numeric($s)) {
            return null;
        }
        $wert = round((float) $s, 2);

        return $wert >= 1 && $wert <= 6 && abs($wert * 20 - round($wert * 20)) < 1e-6 ? $wert : null;
    }

    /** null = leer (Standard 100), false = ungültig; Werte ≤ 1 gelten als Anteil (0.5 → 50 %). */
    public function gewicht(string $s): float|false|null
    {
        $s = str_replace([',', '%'], ['.', ''], trim($s));
        if ($s === '') {
            return null;
        }
        if (! is_numeric($s)) {
            return false;
        }
        $wert = (float) $s;
        $wert = $wert > 0 && $wert <= 1 ? $wert * 100 : $wert;

        return $wert >= 0 && $wert <= 100 ? round($wert, 2) : false;
    }

    /**
     * Fach/Modul zuordnen: Modulnummer im Text, exakter Name/Kürzel (auch ohne Leerzeichen, Umlaute als ae/oe/ue),
     * bekannte Fachnamen und Kürzel, sonst ähnlichster Name (unsicher). Mehrdeutige Treffer sind unsicher.
     *
     * @param  list<array{wert: string, label: string, nummer: ?string, namen: list<string>, kompakt: list<string>}>  $katalog
     * @return array{0: ?string, 1: bool}
     */
    public function bezug(string $roh, array $katalog): array
    {
        $n = $this->norm($roh);
        if ($n === '') {
            return [null, false];
        }
        if (preg_match('/(?:^|[^a-z0-9])(?:m|ük|uek|modul)?\s*-?\s*([a-z]{0,3}\d{2,4}[a-z]?)(?:[^a-z0-9]|$)/u', $n, $m)) {
            foreach ($katalog as $k) {
                if ($k['nummer'] !== null && $k['nummer'] === $m[1]) {
                    return [$k['wert'], true];
                }
            }
        }

        $varianten = $this->varianten($roh);
        $treffer = fn (callable $passt) => array_values(array_unique(array_column(array_filter($katalog, fn (array $k) => array_filter($k['kompakt'], $passt) !== []), 'wert')));
        foreach ($varianten as $v) {
            if ($werte = $treffer(fn (string $name) => $name === $v)) {
                return [$werte[0], count($werte) === 1];
            }
        }
        foreach ($varianten as $v) {
            $gruppe = $this->gruppe($v);
            if ($gruppe !== null) {
                $werte = $treffer(fn (string $name) => $this->gruppe($name) === $gruppe);

                return $werte ? [$werte[0], count($werte) === 1] : [null, false];
            }
        }

        $v = $varianten[0] ?? '';
        $best = null;
        $bestWert = 0.0;
        foreach ($katalog as $k) {
            foreach ($k['kompakt'] as $name) {
                if (mb_strlen($name) < 2) {
                    continue;
                }
                similar_text($v, $name, $prozent);
                if ((str_contains($v, $name) && mb_strlen($name) >= 4) || (mb_strlen($v) >= 4 && str_starts_with($name, $v))) {
                    $prozent = max($prozent, 90.0);
                }
                if ($prozent > $bestWert) {
                    [$best, $bestWert] = [$k['wert'], $prozent];
                }
            }
        }
        if ($bestWert >= 75) {
            return [$best, false];
        }

        // OCR-Fehler in bekannten Fachnamen («Enalisch», «Allemeinbildung»)
        foreach (self::SYNONYME as $g => $namen) {
            similar_text($v, rtrim($namen[0], '*'), $prozent);
            if (mb_strlen($v) >= 5 && $prozent >= 85) {
                $werte = $treffer(fn (string $name) => $this->gruppe($name) === $g);

                return $werte ? [$werte[0], false] : [null, false];
            }
        }

        return [null, false];
    }

    /**
     * BM-Quelle: nur BM-Fächer (und Module). Sonst BM-Fächer weglassen, die es gleichnamig auch ohne BM gibt
     * (Englisch der Berufsfachschule vs. Englisch der BM).
     *
     * @param  list<array<string, mixed>>  $katalog
     * @return list<array<string, mixed>>
     */
    public function bevorzugt(array $katalog, bool $bm): array
    {
        $istFach = fn (array $k) => str_starts_with((string) $k['wert'], 'fach:');
        if ($bm) {
            return array_values(array_filter($katalog, fn (array $k) => ! $istFach($k) || $k['bms']));
        }
        $andere = array_merge([], ...array_values(array_map(fn (array $k) => $k['kompakt'], array_filter($katalog, fn (array $k) => $istFach($k) && ! $k['bms']))));

        return array_values(array_filter($katalog, fn (array $k) => ! $istFach($k) || ! $k['bms'] || array_intersect($k['kompakt'], $andere) === []));
    }

    /** @return list<array{wert: string, label: string, nummer: ?string, namen: list<string>, kompakt: list<string>, bms: bool}> */
    public function katalog(int $lernenderId): array
    {
        $optionen = $this->noten->formOptionsForLernender($lernenderId);
        $katalog = [];
        foreach ($optionen['module'] as $m) {
            $nummer = $this->norm((string) $m->modul_nummer);
            $katalog[] = ['wert' => 'modul:'.$m->modul_id, 'label' => trim($m->modul_nummer.' '.$m->titel), 'nummer' => $nummer,
                'namen' => [$this->norm($m->titel), $this->norm($m->modul_nummer.' '.$m->titel)],
                'kompakt' => array_values(array_filter([ZeugnisText::kompakt($m->titel), ZeugnisText::kompakt($m->modul_nummer.' '.$m->titel)])),
                'bms' => false];
        }
        foreach ($optionen['faecher'] as $f) {
            $katalog[] = ['wert' => 'fach:'.$f->fach_id, 'label' => $f->name, 'nummer' => null,
                'namen' => array_values(array_filter([$this->norm($f->name), $this->norm((string) $f->kurzname)])),
                'kompakt' => array_values(array_filter([ZeugnisText::kompakt($f->name), ZeugnisText::kompakt((string) $f->kurzname)])),
                'bms' => $f->track_typ === 'BMS' || $f->kategorie?->code === 'BMS'];
        }

        return $katalog;
    }

    /**
     * Schreibweisen eines Bezugs: ganz, ohne Schulnetz-Marker «(r)»/«(K)», Klammerinhalt, Kürzel am Ende («…ArbeitenIDAF»).
     *
     * @return list<string>
     */
    private function varianten(string $roh): array
    {
        $ohne = trim(preg_replace('/\((?:r|k)\)/iu', ' ', $roh) ?? $roh);
        $v = [ZeugnisText::kompakt($ohne)];
        if (preg_match_all('/\(([^()]{1,40})\)/u', $ohne, $m)) {
            $v[] = ZeugnisText::kompakt((string) preg_replace('/\([^()]*\)/u', ' ', $ohne));
            array_push($v, ...array_map(ZeugnisText::kompakt(...), $m[1]));
        }
        if (preg_match('/^(.*\p{Ll})\s*(\p{Lu}{2,6})$/u', $ohne, $m)) {
            array_push($v, ZeugnisText::kompakt($m[2]), ZeugnisText::kompakt($m[1]));
        }

        return array_values(array_unique(array_filter($v)));
    }

    private function gruppe(string $kompakt): ?int
    {
        foreach (self::SYNONYME as $g => $namen) {
            foreach ($namen as $name) {
                if ($kompakt === $name || (str_ends_with($name, '*') && str_starts_with($kompakt, rtrim($name, '*')))) {
                    return $g;
                }
            }
        }

        return null;
    }

    /**
     * Kopfzeile in den ersten zehn Zeilen suchen; ohne Kopf Spalten nach Inhalt erraten.
     *
     * @param  list<list<string>>  $tabelle
     * @return array{0: int, 1: array<string, int>}
     */
    private function spalten(array $tabelle, array $katalog): array
    {
        foreach (array_slice($tabelle, 0, 10, true) as $i => $zeile) {
            $treffer = [];
            foreach ($zeile as $j => $zelle) {
                $n = rtrim($this->norm($zelle), ':');
                foreach (self::KOPF as $spalte => $namen) {
                    if (! isset($treffer[$spalte]) && in_array($n, $namen, true)) {
                        $treffer[$spalte] = $j;
                        break;
                    }
                }
            }
            if (isset($treffer['note']) && count($treffer) >= 2) {
                return [$i, $treffer];
            }
        }

        $zaehler = [];
        foreach (array_slice($tabelle, 0, 30) as $zeile) {
            foreach ($zeile as $j => $zelle) {
                $zelle = trim($zelle);
                $zaehler[$j]['datum'] = ($zaehler[$j]['datum'] ?? 0) + (int) ($this->datum($zelle) !== null);
                $zaehler[$j]['note'] = ($zaehler[$j]['note'] ?? 0) + (int) ($this->note($zelle) !== null);
                $zaehler[$j]['prozent'] = ($zaehler[$j]['prozent'] ?? 0) + (int) str_contains($zelle, '%');
                $zaehler[$j]['text'] = ($zaehler[$j]['text'] ?? 0) + (preg_match('/\p{L}{3,}/u', $zelle) ? mb_strlen($zelle) : 0);
                $zaehler[$j]['katalog'] = ($zaehler[$j]['katalog'] ?? 0) + (int) ($zelle !== '' && ! is_numeric($zelle) && $this->bezug($zelle, $katalog)[0] !== null);
            }
        }
        $wahl = fn (string $art, array $ohne = []) => collect($zaehler)->except($ohne)->filter(fn ($z) => $z[$art] > 0)->sortByDesc($art)->keys()->first();

        $treffer = array_filter(['datum' => $wahl('datum')], fn ($v) => $v !== null);
        $treffer['note'] = collect($zaehler)->except(array_values($treffer))->filter(fn ($z) => $z['note'] > 0)->keys()->last();
        $treffer['gewicht'] = $wahl('prozent', array_values(array_filter($treffer, fn ($v) => $v !== null)));
        $belegt = array_values(array_filter($treffer, fn ($v) => $v !== null));
        $treffer['bezug'] = $wahl('katalog', $belegt) ?? $wahl('text', $belegt);
        $treffer['titel'] = $wahl('text', array_values(array_filter($treffer, fn ($v) => $v !== null)));

        return [-1, array_filter($treffer, fn ($v) => $v !== null)];
    }

    /** @return array<string, true> Schlüssel «typ:id|datum|note» bereits erfasster Noten */
    private function vorhandene(int $lernenderId): array
    {
        return DB::table('noten as n')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->get(['n.fach_id', 'mb.modul_id', 'n.pruefungsdatum', 'n.note_wert'])
            ->mapWithKeys(fn ($n) => [($n->fach_id ? 'fach:'.$n->fach_id : 'modul:'.$n->modul_id).'|'.substr((string) $n->pruefungsdatum, 0, 10).'|'.number_format((float) $n->note_wert, 2) => true])
            ->all();
    }

    private function norm(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($s))) ?? '');
    }
}
