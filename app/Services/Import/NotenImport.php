<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Note;
use App\Services\Noten\NoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
        $this->noten->beginBatch();
        try {
            $format = count($tabelle[0] ?? []) === 1 && in_array($tabelle[0][0], [Schulnetz::AKTUELLE_NOTEN, Schulnetz::ZEUGNISNOTEN], true) ? $tabelle[0][0] : null;
            $katalog = $format ? $this->bevorzugt($this->katalog($lernenderId), false) : $this->katalog($lernenderId);
            [$kopf, $spalten] = $this->spalten($tabelle, $katalog);
            $vorhanden = $this->vorhandene($lernenderId);
            $fachErlaubt = $this->noten->fachErlaubtAm($lernenderId); // Track am Prüfungsdatum gültig
            $imFile = [];

            $zeilen = [];
            foreach ($tabelle as $i => $zelle) {
                if ($i <= $kopf || count($zeilen) >= TabellenLeser::MAX_ZEILEN) {
                    continue;
                }
                $roh = fn (string $s) => isset($spalten[$s]) ? trim((string) ($zelle[$spalten[$s]] ?? '')) : '';
                $datum = WertParser::datum($roh('datum'));
                $note = WertParser::note($roh('note'));
                $gewicht = WertParser::gewicht($roh('gewicht'));
                [$bezug, $sicher] = $this->bezug($roh('bezug') !== '' ? $roh('bezug') : $roh('titel'), $katalog);

                // Notenzeile = Note vorhanden oder Datum mit erkanntem Fach/Modul; Titel-, Kopf- und Fusszeilen fallen weg
                if ($note === null && ($datum === null || $bezug === null)) {
                    continue;
                }

                $bewertet = $this->bewerten(
                    $datum, $bezug, $note, $gewicht, $roh('datum'), $roh('note'), $sicher, $lernenderId,
                    $fachErlaubt, $vorhanden, $imFile, WertParser::datumMehrdeutig($roh('datum')), WertParser::gewichtMehrdeutig($roh('gewicht')),
                );

                $zeilen[] = [
                    'nr' => $i + 1,
                    'datum' => $datum,
                    'bezug' => $bezug,
                    'bezug_roh' => mb_substr($roh('bezug'), 0, 150),
                    'titel' => mb_substr($roh('titel'), 0, 150),
                    'note' => $note,
                    'gewicht' => $gewicht === false || $gewicht === null ? 100 : $gewicht,
                    ...$bewertet,
                    'sicher' => $sicher,
                    'uebernehmen' => in_array($bewertet['status'], ['ok', 'warnung'], true),
                ];
            }

            return ['zeilen' => $zeilen, 'erkannt' => $format ? [] : $spalten, 'format' => $format];
        } finally {
            $this->noten->endBatch();
        }
    }

    /**
     * Bearbeitete Vorschau-Zeilen nach Korrekturen in der Vorschau serverseitig neu prüfen («Erneut prüfen»):
     * gleiche Regeln wie vorschau(), aber der Bezug ist bereits eine feste Auswahl. Die Sicherheit der Zuordnung
     * (Feld «sicher») wird aus der vorherigen Vorschau übernommen und nur dann auf sicher gesetzt, wenn die Person
     * den Bezug tatsächlich geändert hat – ein Klick auf «Erneut prüfen» ohne Korrektur darf ein nur geratenes
     * Fach/Modul nicht stillschweigend als «bereit» einstufen.
     *
     * @param  list<array<string, mixed>>  $zeilen
     * @param  list<array<string, mixed>>  $vorherigeZeilen  letzter bekannter Stand (Session) mit Feldern nr/bezug/sicher
     * @return list<array<string, mixed>>
     */
    public function pruefeZeilen(array $zeilen, int $lernenderId, array $vorherigeZeilen = []): array
    {
        $this->noten->beginBatch();
        try {
            $vorhanden = $this->vorhandene($lernenderId);
            $fachErlaubt = $this->noten->fachErlaubtAm($lernenderId);
            $imFile = [];
            $vorherIndex = [];
            foreach ($vorherigeZeilen as $vz) {
                $vorherIndex[(int) ($vz['nr'] ?? 0)] = $vz;
            }

            $out = [];
            foreach ($zeilen as $i => $z) {
                $datumRoh = trim((string) ($z['datum'] ?? ''));
                $datum = $datumRoh !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datumRoh) === 1 ? $datumRoh : null;
                $bezugRoh = (string) ($z['bezug'] ?? '');
                $bezug = $bezugRoh !== '' && preg_match('/^(fach|modul):\d+$/', $bezugRoh) === 1 ? $bezugRoh : null;
                $noteRoh = trim((string) ($z['note'] ?? ''));
                $note = WertParser::note($noteRoh);
                $gewichtRoh = trim((string) ($z['gewicht'] ?? ''));
                $gewicht = WertParser::gewicht($gewichtRoh);

                $nr = (int) ($z['nr'] ?? ($i + 1));
                $vorher = $vorherIndex[$nr] ?? null;
                $vorherBezug = $vorher['bezug'] ?? null;
                $vorherSicher = (bool) ($vorher['sicher'] ?? false);
                $sicher = $vorherBezug !== null && $vorherBezug !== $bezug ? true : $vorherSicher;

                $bewertet = $this->bewerten(
                    $datum, $bezug, $note, $gewicht, $datumRoh, $noteRoh, $sicher, $lernenderId,
                    $fachErlaubt, $vorhanden, $imFile, false, WertParser::gewichtMehrdeutig($gewichtRoh),
                );

                $out[] = [
                    'nr' => $nr,
                    'datum' => $datum,
                    'bezug' => $bezug,
                    'bezug_roh' => mb_substr((string) ($z['bezug_roh'] ?? ''), 0, 150),
                    'titel' => mb_substr(trim((string) ($z['titel'] ?? '')), 0, 150),
                    'note' => $note,
                    'gewicht' => $gewicht === false || $gewicht === null ? 100 : $gewicht,
                    ...$bewertet,
                    'sicher' => $sicher,
                    'uebernehmen' => ! empty($z['uebernehmen']) && $bewertet['status'] !== 'fehler',
                ];
            }

            return $out;
        } finally {
            $this->noten->endBatch();
        }
    }

    /**
     * Status und Meldung einer Zeile – einzige Stelle, die vorschau() und pruefeZeilen() («Erneut prüfen») teilen.
     * Prüft zusätzlich zu Datum/Note/Gewicht/Bezug die DB-Regeln über NoteService::pruefeZeile()
     * (Lehrbeginn/-ende, Semester am Datum vorhanden, Kategorie) und Dubletten innerhalb der Datei.
     *
     * @param  array<string, true>  $vorhanden  Schlüssel bereits gespeicherter Noten (vorhandene())
     * @param  array<string, true>  $imFile  Schlüssel bereits gesehener Zeilen dieser Datei (wird befüllt)
     * @return array{status: string, meldung: ?string}
     */
    private function bewerten(
        ?string $datum, ?string $bezug, ?float $note, float|false|null $gewicht, string $datumRoh, string $noteRoh,
        bool $sicher, int $lernenderId, \Closure $fachErlaubt, array $vorhanden, array &$imFile,
        bool $datumMehrdeutig, bool $gewichtMehrdeutig,
    ): array {
        $trackFehlt = $datum !== null && $bezug !== null && str_starts_with($bezug, 'fach:')
            && ! $fachErlaubt($datum, (int) substr($bezug, 5));

        $dbFehler = null;
        if (! $trackFehlt && $datum !== null && $bezug !== null && $note !== null && $gewicht !== false) {
            [$typ, $id] = explode(':', $bezug);
            try {
                $this->noten->pruefeZeile([
                    'typ' => $typ,
                    'fach_id' => $typ === 'fach' ? (int) $id : null,
                    'modul_id' => $typ === 'modul' ? (int) $id : null,
                    'pruefungsdatum' => $datum,
                    'note_wert' => $note,
                    'gewichtung_prozent' => $gewicht ?? 100,
                ], $lernenderId);
            } catch (ValidationException $e) {
                $dbFehler = collect($e->errors())->flatten()->first();
            }
        }

        $schluessel = $bezug !== null && $datum !== null && $note !== null ? $bezug.'|'.$datum.'|'.number_format($note, 2) : null;
        $dublette = $schluessel !== null && isset($vorhanden[$schluessel]);
        $dateiDublette = ! $dublette && $schluessel !== null && isset($imFile[$schluessel]);
        if ($schluessel !== null) {
            $imFile[$schluessel] = true;
        }

        // Interner Code statt der (übersetzten) Meldung, damit $status davon unabhängig bleibt.
        $code = match (true) {
            $datum === null => $datumRoh === '' ? 'datum_fehlt' : 'datum_unbekannt',
            $note === null => $noteRoh === '' ? 'note_fehlt' : 'note_ungueltig',
            $gewicht === false => 'gewicht_ungueltig',
            $bezug === null => 'bezug_unbekannt',
            $dublette => 'vorhanden',
            $dateiDublette => 'datei_doppelt',
            $trackFehlt => 'track_fehlt',
            $dbFehler !== null => 'db_regel',
            ! $sicher => 'pruefen',
            $datumMehrdeutig => 'datum_mehrdeutig',
            $gewichtMehrdeutig => 'gewicht_mehrdeutig',
            default => null,
        };
        $meldung = match ($code) {
            null => null,
            'datum_fehlt' => __('Datum fehlt'),
            'datum_unbekannt' => __('Datum nicht erkannt'),
            'note_fehlt' => __('Note fehlt'),
            'note_ungueltig' => __('Note ungültig'),
            'gewicht_ungueltig' => __('Gewicht ungültig'),
            'bezug_unbekannt' => __('Fach/Modul nicht erkannt'),
            'vorhanden' => __('bereits erfasst'),
            'datei_doppelt' => __('mehrfach in dieser Datei'),
            'track_fehlt' => __('Track am Datum nicht aktiv'),
            'db_regel' => $dbFehler,
            'pruefen' => __('Zuordnung prüfen'),
            'datum_mehrdeutig' => __('Datum mehrdeutig (Tag/Monat vertauschbar) – bitte prüfen'),
            'gewicht_mehrdeutig' => __('Gewicht als Anteil gedeutet (z. B. 0.5 → 50 %) – bitte prüfen'),
        };
        $status = match ($code) {
            null => 'ok',
            'vorhanden', 'datei_doppelt' => 'doppelt',
            'pruefen' => 'pruefen',
            'datum_mehrdeutig', 'gewicht_mehrdeutig' => 'warnung',
            default => 'fehler',
        };

        return ['status' => $status, 'meldung' => $meldung];
    }

    /**
     * Speichert nur, wenn alle ausgewählten Zeilen gültig sind (alles oder nichts) – sonst nichts und je
     * Zeilenfehler zurück, damit «prüfen → korrigieren → speichern» ohne Teilübernahme funktioniert.
     *
     * @param  list<array<string, mixed>>  $zeilen  bearbeitete Vorschau (nur markierte werden übernommen)
     * @return array{neu: int, fehler: list<string>}
     */
    public function importieren(array $zeilen, int $lernenderId, int $benutzerId): array
    {
        $this->noten->beginBatch();
        try {
            return $this->importierenInBatch($zeilen, $lernenderId, $benutzerId);
        } finally {
            $this->noten->endBatch();
        }
    }

    /** @return array{neu: int, fehler: list<string>} */
    private function importierenInBatch(array $zeilen, int $lernenderId, int $benutzerId): array
    {
        $bereit = [];
        $fehler = [];
        // Dubletten (bereits gespeichert oder mehrfach in der ausgewählten Auswahl) müssen hier – direkt vor dem
        // Schreiben – erneut geprüft werden: die Vorschau markiert sie nur, verhindert das Ankreuzen aber nicht.
        $vorhanden = $this->vorhandene($lernenderId);
        $imFile = [];

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
            ], [], ['datum' => __('Datum'), 'bezug' => __('Fach/Modul'), 'note' => __('Note'), 'gewicht' => __('Gewicht')]);
            if ($pruefung->fails()) {
                $fehler[] = __('Zeile :nr: :fehler', ['nr' => $nr, 'fehler' => $pruefung->errors()->first()]);

                continue;
            }

            $datum = (string) $z['datum'];
            $note = (float) $z['note'];
            $bezug = (string) $z['bezug'];
            $schluessel = $bezug.'|'.$datum.'|'.number_format($note, 2);
            if (isset($vorhanden[$schluessel])) {
                $fehler[] = __('Zeile :nr: :fehler', ['nr' => $nr, 'fehler' => __('bereits erfasst')]);

                continue;
            }
            if (isset($imFile[$schluessel])) {
                $fehler[] = __('Zeile :nr: :fehler', ['nr' => $nr, 'fehler' => __('mehrfach in dieser Datei')]);

                continue;
            }
            $imFile[$schluessel] = true;

            [$typ, $id] = explode(':', $bezug);
            $eingabe = [
                'typ' => $typ,
                'fach_id' => $typ === 'fach' ? (int) $id : null,
                'modul_id' => $typ === 'modul' ? (int) $id : null,
                'titel' => filled($z['titel'] ?? null) ? (string) $z['titel'] : null,
                'pruefungsdatum' => $datum,
                'note_wert' => $note,
                'gewichtung_prozent' => $z['gewicht'] ?? 100,
            ];
            try {
                $this->noten->pruefeZeile($eingabe, $lernenderId);
            } catch (ValidationException $e) {
                $fehler[] = __('Zeile :nr: :fehler', ['nr' => $nr, 'fehler' => collect($e->errors())->flatten()->first()]);

                continue;
            }
            $bereit[] = $eingabe;
        }

        if ($bereit === [] || $fehler !== []) {
            return ['neu' => 0, 'fehler' => $fehler];
        }

        $neu = 0;
        try {
            DB::transaction(function () use ($bereit, $lernenderId, $benutzerId, &$neu) {
                foreach ($bereit as $eingabe) {
                    $daten = $this->noten->normalizeForSave($eingabe, $lernenderId);
                    Note::create([
                        'lernender_id' => $lernenderId,
                        ...array_intersect_key($daten, array_flip(['kategorie_id', 'semester_id', 'fach_id', 'modul_belegung_id', 'titel', 'pruefungsdatum', 'note_wert', 'gewichtung_prozent'])),
                        'erfasst_von_benutzer_id' => $benutzerId,
                    ]);
                    $neu++;
                }
            });
        } catch (ValidationException $e) {
            // normalizeForSave() prüft (pruefeZeile) innerhalb der Transaktion erneut; eine dort geworfene
            // Ausnahme hat die Transaktion bereits zurückgerollt – als Importfehler statt als Systemfehler melden.
            return ['neu' => 0, 'fehler' => [collect($e->errors())->flatten()->first()]];
        }

        return ['neu' => $neu, 'fehler' => []];
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

    /** @see WertParser::datum() */
    public function datum(string $s): ?string
    {
        return WertParser::datum($s);
    }

    /** @see WertParser::note() */
    public function note(string $s): ?float
    {
        return WertParser::note($s);
    }

    /** @see WertParser::gewicht() */
    public function gewicht(string $s): float|false|null
    {
        return WertParser::gewicht($s);
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
