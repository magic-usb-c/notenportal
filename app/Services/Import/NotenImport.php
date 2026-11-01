<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Note;
use App\Services\Noten\NoteService;
use App\Support\Csv;
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
        'note' => ['note', 'noten', 'notenwert', 'schulnote', 'bewertung', 'wert', 'grade', 'resultat'],
        'gewicht' => ['gewicht', 'gewichtung', 'gew', 'gew.', 'gew. %', 'gewichtung %', 'gewicht %', '%', 'faktor', 'anteil'],
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
     * @return array{zeilen: list<array<string, mixed>>, erkannt: array<string, int>, format: ?string, abgeschnitten: bool}
     */
    public function vorschau(array $tabelle, int $lernenderId): array
    {
        $this->noten->beginBatch();
        try {
            $format = count($tabelle[0] ?? []) === 1 && in_array($tabelle[0][0], [Schulnetz::AKTUELLE_NOTEN, Schulnetz::ZEUGNISNOTEN], true) ? $tabelle[0][0] : null;
            $katalog = $format ? $this->bevorzugt($this->katalog($lernenderId), false) : $this->katalog($lernenderId);
            [$kopf, $spalten, $faktor] = $this->spalten($tabelle, $katalog);
            $vorhanden = $this->vorhandene($lernenderId);
            $stufenFaecher = $this->stufenFaecher();
            $fachErlaubt = $this->noten->fachErlaubtAm($lernenderId); // Track am Prüfungsdatum gültig
            $imFile = [];

            $zeilen = [];
            $abgeschnitten = false;
            foreach ($tabelle as $i => $zelle) {
                if ($i <= $kopf) {
                    continue;
                }
                $roh = fn (string $s) => isset($spalten[$s]) ? trim((string) ($zelle[$spalten[$s]] ?? '')) : '';
                $datum = WertParser::datum($roh('datum'));
                $note = WertParser::note($roh('note'));
                $stufe = WertParser::stufe($roh('note'));
                $gewicht = $faktor ? WertParser::faktor($roh('gewicht')) : WertParser::gewicht($roh('gewicht'));
                [$bezug, $sicher] = $this->bezug($roh('bezug') !== '' ? $roh('bezug') : $roh('titel'), $katalog);

                // Notenzeile = Note (Zahl oder Stufe) vorhanden oder Datum mit erkanntem Fach/Modul; Titel-, Kopf- und Fusszeilen fallen weg
                if ($note === null && $stufe === null && ($datum === null || $bezug === null)) {
                    continue;
                }
                if (count($zeilen) >= TabellenLeser::MAX_ZEILEN) {
                    $abgeschnitten = true; // weitere Notenzeilen folgen, werden aber nicht mehr gelesen

                    break;
                }

                $bewertet = $this->bewerten(
                    $datum, $bezug, $note, $stufe, $bezug !== null && isset($stufenFaecher[$bezug]), $gewicht, $roh('datum'), $roh('note'), $sicher, $lernenderId,
                    $fachErlaubt, $vorhanden, $imFile, WertParser::datumMehrdeutig($roh('datum')), ! $faktor && WertParser::gewichtMehrdeutig($roh('gewicht')),
                );

                $zeilen[] = [
                    'nr' => $i + 1,
                    'datum' => $datum,
                    'bezug' => $bezug,
                    'bezug_roh' => mb_substr($roh('bezug'), 0, 150),
                    'titel' => mb_substr($roh('titel'), 0, 150),
                    'note' => $note ?? $stufe,
                    'gewicht' => $this->gewichtFeld($gewicht, $roh('gewicht'), $faktor),
                    ...$bewertet,
                    'sicher' => $sicher,
                    'uebernehmen' => in_array($bewertet['status'], ['ok', 'warnung'], true),
                ];
            }

            return ['zeilen' => $zeilen, 'erkannt' => $format ? [] : $spalten, 'format' => $format, 'abgeschnitten' => $abgeschnitten];
        } finally {
            $this->noten->endBatch();
        }
    }

    /**
     * Bearbeitete Vorschau-Zeilen nach Korrekturen in der Vorschau serverseitig neu prüfen («Erneut prüfen»):
     * gleiche Regeln wie vorschau(), aber der Bezug ist bereits eine feste Auswahl. Die Sicherheit der Zuordnung
     * (Feld «sicher») wird aus der vorherigen Vorschau übernommen und nur dann auf sicher gesetzt, wenn die Person
     * den Bezug tatsächlich auf ein Fach/Modul gesetzt oder gewechselt hat – ein Klick auf «Erneut prüfen» ohne
     * Korrektur darf ein nur geratenes Fach/Modul nicht stillschweigend als «bereit» einstufen. Eine Zeile, die
     * gar nicht zuordenbar war, zählt dabei mit: wer sie von Hand zuordnet, wählt ebenso bewusst.
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
            $stufenFaecher = $this->stufenFaecher();
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
                $stufe = WertParser::stufe($noteRoh);
                $gewichtRoh = trim((string) ($z['gewicht'] ?? ''));
                $gewicht = WertParser::gewicht($gewichtRoh);

                $nr = (int) ($z['nr'] ?? ($i + 1));
                $vorher = $vorherIndex[$nr] ?? null;
                $vorherBezug = $vorher['bezug'] ?? null;
                $vorherSicher = (bool) ($vorher['sicher'] ?? false);
                $sicher = $bezug !== null && $vorherBezug !== $bezug ? true : $vorherSicher;

                $bewertet = $this->bewerten(
                    $datum, $bezug, $note, $stufe, $bezug !== null && isset($stufenFaecher[$bezug]), $gewicht, $datumRoh, $noteRoh, $sicher, $lernenderId,
                    $fachErlaubt, $vorhanden, $imFile, false, WertParser::gewichtMehrdeutig($gewichtRoh),
                );

                $out[] = [
                    'nr' => $nr,
                    'datum' => $datum,
                    'bezug' => $bezug,
                    'bezug_roh' => mb_substr((string) ($z['bezug_roh'] ?? ''), 0, 150),
                    'titel' => mb_substr(trim((string) ($z['titel'] ?? '')), 0, 150),
                    'note' => $note ?? $stufe,
                    'gewicht' => $this->gewichtFeld($gewicht, $gewichtRoh, false),
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
     * Wert für das Gewichtsfeld der Vorschau. Leer heisst Standard 100 %. Ein ungültiges Gewicht bleibt als Text im
     * Feld stehen (ein Faktor in Prozent umgerechnet, «2» also «200»), damit «Erneut prüfen» und eine fehlgeschlagene
     * Übernahme es wieder als ungültig erkennen, statt still 100 % einzusetzen.
     */
    private function gewichtFeld(float|false|null $gewicht, string $roh, bool $faktor): float|string
    {
        if ($gewicht === null) {
            return 100;
        }
        if ($gewicht !== false) {
            return $gewicht;
        }
        $zahl = str_replace(',', '.', trim($roh));

        return $faktor && is_numeric($zahl) ? (string) round((float) $zahl * 100, 2) : mb_substr(trim($roh), 0, 20);
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
        ?string $datum, ?string $bezug, ?float $note, ?string $stufe, bool $stufenFach, float|false|null $gewicht, string $datumRoh, string $noteRoh,
        bool $sicher, int $lernenderId, \Closure $fachErlaubt, array $vorhanden, array &$imFile,
        bool $datumMehrdeutig, bool $gewichtMehrdeutig,
    ): array {
        $trackFehlt = $datum !== null && $bezug !== null && str_starts_with($bezug, 'fach:')
            && ! $fachErlaubt($datum, (int) substr($bezug, 5));

        // Zahl oder Stufe – je nach Skala des Fachs; ein Modul hat immer eine Zahl.
        $wertCode = match (true) {
            $stufenFach => $stufe !== null ? null : ($noteRoh === '' ? 'note_fehlt' : 'stufe_erwartet'),
            $note !== null => null,
            $stufe !== null => $bezug === null ? null : 'zahl_erwartet',
            default => $noteRoh === '' ? 'note_fehlt' : 'note_ungueltig',
        };
        $wert = $stufenFach ? ($stufe !== null ? 's:'.$stufe : null) : ($note !== null ? number_format($note, 2) : null);

        $dbFehler = null;
        if (! $trackFehlt && $datum !== null && $bezug !== null && $wertCode === null && $gewicht !== false) {
            [$typ, $id] = explode(':', $bezug);
            try {
                $this->noten->pruefeZeile([
                    'typ' => $typ,
                    'fach_id' => $typ === 'fach' ? (int) $id : null,
                    'modul_id' => $typ === 'modul' ? (int) $id : null,
                    'pruefungsdatum' => $datum,
                    'note_wert' => $stufenFach ? null : $note,
                    'note_stufe' => $stufenFach ? $stufe : null,
                    'gewichtung_prozent' => $gewicht ?? 100,
                ], $lernenderId);
            } catch (ValidationException $e) {
                $dbFehler = collect($e->errors())->flatten()->first();
            }
        }

        $schluessel = $bezug !== null && $datum !== null && $wert !== null ? $bezug.'|'.$datum.'|'.$wert : null;
        $dublette = $schluessel !== null && isset($vorhanden[$schluessel]);
        $dateiDublette = ! $dublette && $schluessel !== null && isset($imFile[$schluessel]);
        if ($schluessel !== null) {
            $imFile[$schluessel] = true;
        }

        // Interner Code statt der (übersetzten) Meldung, damit $status davon unabhängig bleibt.
        $code = match (true) {
            $datum === null => $datumRoh === '' ? 'datum_fehlt' : 'datum_unbekannt',
            $wertCode !== null => $wertCode,
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
            'stufe_erwartet' => __('Stufe A, B, C oder d erwartet'),
            'zahl_erwartet' => __('Note zwischen 1 und 6 erwartet'),
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

    /** @return array{neu: int, fehler: list<string>, semester_ids: list<int>} */
    private function importierenInBatch(array $zeilen, int $lernenderId, int $benutzerId): array
    {
        $bereit = [];
        $fehler = [];
        // Dubletten (bereits gespeichert oder mehrfach in der ausgewählten Auswahl) müssen hier – direkt vor dem
        // Schreiben – erneut geprüft werden: die Vorschau markiert sie nur, verhindert das Ankreuzen aber nicht.
        $vorhanden = $this->vorhandene($lernenderId);
        $stufenFaecher = $this->stufenFaecher();
        $imFile = [];

        foreach ($zeilen as $z) {
            if (empty($z['uebernehmen'])) {
                continue;
            }
            $nr = (int) ($z['nr'] ?? 0);
            // Stufenfach (Sport): A/B/C/d statt einer Zahl; die Schreibweise wird vereinheitlicht («b» → «B»).
            $stufenFach = is_string($z['bezug'] ?? null) && isset($stufenFaecher[$z['bezug']]);
            $z['note'] = $stufenFach ? (WertParser::stufe((string) ($z['note'] ?? '')) ?? $z['note'] ?? null) : WertParser::punkt($z['note'] ?? null);
            $z['gewicht'] = WertParser::punkt($z['gewicht'] ?? null);
            $pruefung = Validator::make($z, [
                'datum' => ['required', 'date_format:Y-m-d'],
                'bezug' => ['required', 'regex:/^(fach|modul):\d+$/'],
                'titel' => ['nullable', 'string', 'max:150'],
                'note' => $stufenFach
                    ? ['required', 'string', 'in:'.implode(',', NoteService::STUFEN)]
                    : ['required', 'numeric', 'min:1', 'max:6', 'multiple_of:0.05'],
                'gewicht' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ], [], ['datum' => __('Datum'), 'bezug' => __('Fach/Modul'), 'note' => __('Note'), 'gewicht' => __('Gewicht')]);
            if ($pruefung->fails()) {
                $fehler[] = __('Zeile :nr: :fehler', ['nr' => $nr, 'fehler' => $pruefung->errors()->first()]);

                continue;
            }

            $datum = (string) $z['datum'];
            $bezug = (string) $z['bezug'];
            $schluessel = $bezug.'|'.$datum.'|'.($stufenFach ? 's:'.$z['note'] : number_format((float) $z['note'], 2));
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
                'note_wert' => $stufenFach ? null : (float) $z['note'],
                'note_stufe' => $stufenFach ? (string) $z['note'] : null,
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
            return ['neu' => 0, 'fehler' => $fehler, 'semester_ids' => []];
        }

        $neu = 0;
        $semesterIds = [];
        try {
            DB::transaction(function () use ($bereit, $lernenderId, $benutzerId, &$neu, &$semesterIds) {
                foreach ($bereit as $eingabe) {
                    $daten = $this->noten->normalizeForSave($eingabe, $lernenderId);
                    Note::create([
                        'lernender_id' => $lernenderId,
                        ...array_intersect_key($daten, array_flip(['kategorie_id', 'semester_id', 'fach_id', 'modul_belegung_id', 'titel', 'pruefungsdatum', 'note_wert', 'note_stufe', 'gewichtung_prozent'])),
                        'erfasst_von_benutzer_id' => $benutzerId,
                    ]);
                    $neu++;
                    $semesterIds[(int) $daten['semester_id']] = true;
                }
            });
        } catch (ValidationException $e) {
            // normalizeForSave() prüft (pruefeZeile) innerhalb der Transaktion erneut; eine dort geworfene
            // Ausnahme hat die Transaktion bereits zurückgerollt – als Importfehler statt als Systemfehler melden.
            return ['neu' => 0, 'fehler' => [collect($e->errors())->flatten()->first()], 'semester_ids' => []];
        }

        return ['neu' => $neu, 'fehler' => [], 'semester_ids' => array_keys($semesterIds)];
    }

    /** CSV-Vorlage mit Kopfzeile und je einem Beispiel pro Fach/Modul-Art. */
    public function vorlage(int $lernenderId): string
    {
        $beispiele = collect($this->katalog($lernenderId))->take(2)->map(fn ($k) => $k['label'])->all();
        $zeilen = [['Datum', 'Fach/Modul', 'Titel', 'Note', 'Gewicht']];
        foreach ($beispiele as $i => $label) {
            $zeilen[] = [now()->subDays(7 * ($i + 1))->format('d.m.Y'), $label, 'Prüfung '.($i + 1), $i ? '5.0' : '4.5', '100'];
        }

        // fputcsv statt implode: ein Semikolon im Modultitel bleibt so ein einziges Feld (Anführungszeichen),
        // Csv::safe neutralisiert Formelanfänge (CWE-1236) wie in allen anderen Exporten.
        $h = fopen('php://temp', 'w+');
        foreach ($zeilen as $z) {
            fputcsv($h, array_map(Csv::safe(...), $z), ';', '"', '', "\r\n");
        }
        rewind($h);
        $csv = (string) stream_get_contents($h);
        fclose($h);

        return "\xEF\xBB\xBF".$csv;
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
     * @return array{0: int, 1: array<string, int>, 2: bool} Kopfzeile, Spalten, Gewichtsspalte ist ein Faktor (1 = 100 %)
     */
    private function spalten(array $tabelle, array $katalog): array
    {
        foreach (array_slice($tabelle, 0, 10, true) as $i => $zeile) {
            $treffer = [];
            $faktor = false;
            foreach ($zeile as $j => $zelle) {
                $art = $this->kopfArt($zelle);
                if ($art !== null && ! isset($treffer[$art[0]])) {
                    $treffer[$art[0]] = $j;
                    $faktor = $art[0] === 'gewicht' ? $art[1] : $faktor;
                }
            }
            if (isset($treffer['note']) && count($treffer) >= 2) {
                return [$i, $treffer, $faktor];
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

        return [-1, array_filter($treffer, fn ($v) => $v !== null), false];
    }

    /**
     * Spaltenart einer Kopfzelle. Zuerst der rohe Name (so bleibt auch «%» erkannt), dann der bereinigte ohne
     * Klammern, «in», «Prozent» und Satzzeichen («Gewichtung (%)», «Gewicht in %»). Ein «Faktor» oder eine Spanne
     * wie «(1–3)» im Kopf heisst: die Werte sind keine Prozent.
     *
     * @return ?array{0: string, 1: bool} Spalte, Gewichtswerte sind ein Faktor
     */
    private function kopfArt(string $zelle): ?array
    {
        $n = rtrim($this->norm($zelle), ':');
        // Faktor: das Wort «Faktor» oder eine Spanne wie «(1-3)»; «(0-100)» bleibt eine Prozentspalte
        $faktor = str_contains($n, 'faktor')
            || (preg_match('/\(\s*\d+(?:[.,]\d+)?\s*[-–—]\s*(\d+(?:[.,]\d+)?)\s*\)/u', $n, $spanne) === 1 && (float) str_replace(',', '.', $spanne[1]) <= 10);
        $bereinigt = $this->norm(preg_replace('/\([^)]*\)|\b(?:in|prozent|faktor)\b|[%.:]/u', ' ', $n) ?? '');
        foreach ([$n, $bereinigt] as $name) {
            if ($name === '') {
                continue;
            }
            foreach (self::KOPF as $spalte => $namen) {
                if (in_array($name, $namen, true)) {
                    return [$spalte, $spalte === 'gewicht' && $faktor];
                }
            }
        }

        return null;
    }

    /** @return array<string, true> «fach:ID» der Fächer mit Stufen statt Zahlen (Sport) */
    private function stufenFaecher(): array
    {
        return DB::table('faecher')->where('skala', 'stufe')->pluck('fach_id')
            ->mapWithKeys(fn ($id) => ['fach:'.$id => true])->all();
    }

    /** @return array<string, true> Schlüssel «typ:id|datum|note» (bei Stufen «s:B») bereits erfasster Noten */
    private function vorhandene(int $lernenderId): array
    {
        return DB::table('noten as n')
            ->leftJoin('modul_belegungen as mb', 'mb.modul_belegung_id', '=', 'n.modul_belegung_id')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->get(['n.fach_id', 'mb.modul_id', 'n.pruefungsdatum', 'n.note_wert', 'n.note_stufe'])
            ->mapWithKeys(fn ($n) => [($n->fach_id ? 'fach:'.$n->fach_id : 'modul:'.$n->modul_id).'|'.substr((string) $n->pruefungsdatum, 0, 10).'|'.($n->note_wert === null && $n->note_stufe !== null ? 's:'.$n->note_stufe : number_format((float) $n->note_wert, 2)) => true])
            ->all();
    }

    private function norm(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($s))) ?? '');
    }
}
