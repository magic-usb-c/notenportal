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

    public function __construct(private readonly NoteService $noten) {}

    /**
     * @param  list<list<string>>  $tabelle
     * @return array{zeilen: list<array<string, mixed>>, erkannt: array<string, int>}
     */
    public function vorschau(array $tabelle, int $lernenderId): array
    {
        $katalog = $this->katalog($lernenderId);
        [$kopf, $spalten] = $this->spalten($tabelle, $katalog);
        $vorhanden = $this->vorhandene($lernenderId);

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

            if ($datum === null && $note === null) {
                continue;
            }

            $meldung = match (true) {
                $datum === null => 'Datum nicht erkannt',
                $note === null => 'Note ungültig',
                $gewicht === false => 'Gewicht ungültig',
                $bezug === null => 'Fach/Modul nicht erkannt',
                isset($vorhanden[$bezug.'|'.$datum.'|'.number_format($note, 2)]) => 'bereits erfasst',
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

        return ['zeilen' => $zeilen, 'erkannt' => $spalten];
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
     * Fach/Modul zuordnen: Modulnummer im Text, exakter Name/Kürzel, sonst ähnlichster Name (unsicher).
     *
     * @param  list<array{wert: string, label: string, nummer: ?string, namen: list<string>}>  $katalog
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
        foreach ($katalog as $k) {
            if (in_array($n, $k['namen'], true)) {
                return [$k['wert'], true];
            }
        }
        $best = null;
        $bestWert = 0.0;
        foreach ($katalog as $k) {
            foreach ($k['namen'] as $name) {
                similar_text($n, $name, $prozent);
                if ((str_contains($n, $name) && mb_strlen($name) >= 4) || (mb_strlen($n) >= 4 && str_starts_with($name, $n))) {
                    $prozent = max($prozent, 90.0);
                }
                if ($prozent > $bestWert) {
                    [$best, $bestWert] = [$k['wert'], $prozent];
                }
            }
        }

        return $bestWert >= 75 ? [$best, false] : [null, false];
    }

    /** @return list<array{wert: string, label: string, nummer: ?string, namen: list<string>}> */
    public function katalog(int $lernenderId): array
    {
        $optionen = $this->noten->formOptionsForLernender($lernenderId);
        $katalog = [];
        foreach ($optionen['module'] as $m) {
            $nummer = $this->norm((string) $m->modul_nummer);
            $katalog[] = ['wert' => 'modul:'.$m->modul_id, 'label' => trim($m->modul_nummer.' '.$m->titel), 'nummer' => $nummer,
                'namen' => [$this->norm($m->titel), $this->norm($m->modul_nummer.' '.$m->titel)]];
        }
        foreach ($optionen['faecher'] as $f) {
            $katalog[] = ['wert' => 'fach:'.$f->fach_id, 'label' => $f->name, 'nummer' => null,
                'namen' => array_values(array_filter([$this->norm($f->name), $this->norm((string) $f->kurzname)]))];
        }

        return $katalog;
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
