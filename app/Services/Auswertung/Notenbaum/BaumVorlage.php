<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Notenbäume als Vorlage (JSON): exportieren, prüfen, importieren. Gleicher Gedanke wie der Modulkatalog –
 * Rechenwege sind Daten, keine Konstanten. Kategorien stehen als Code (FACH, UEK, BMS, ABU), Fächer als
 * Name; fehlende Fächer legt der Import an und gibt sie dem Lehrberuf bzw. dem Bildungsgang frei.
 *
 * Format: resources/vorlagen/notenbaeume/*.json, beschrieben in docs/notenlogik.md.
 */
final class BaumVorlage
{
    public const string FORMAT = 'notenportal-notenbaum';

    public const int VERSION = 1;

    /** Mitgelieferte Vorlagen. */
    public static function verzeichnis(): string
    {
        return resource_path('vorlagen/notenbaeume');
    }

    /** @return array<string, array{datei: string, name: string, beschreibung: string, bezug: string}> nach Schlüssel (Dateiname ohne .json) */
    public static function mitgeliefert(): array
    {
        $out = [];
        foreach (glob(self::verzeichnis().'/*.json') ?: [] as $datei) {
            $d = json_decode((string) file_get_contents($datei), true);
            if (! is_array($d) || ($d['format'] ?? null) !== self::FORMAT) {
                continue;
            }
            $out[basename($datei, '.json')] = ['datei' => $datei, 'name' => (string) ($d['name'] ?? basename($datei)),
                'beschreibung' => (string) ($d['beschreibung'] ?? ''), 'bezug' => (string) ($d['bezug'] ?? Baum::LEHRBERUF)];
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, mixed> */
    public static function laden(string $schluessel): array
    {
        $liste = self::mitgeliefert();
        if (! isset($liste[$schluessel])) {
            throw new RuntimeException(__('Unbekannte Vorlage «:name».', ['name' => $schluessel]));
        }

        return self::lesen((string) file_get_contents($liste[$schluessel]['datei']));
    }

    /** @return array<string, mixed> geprüfte Vorlage */
    public static function lesen(string $json): array
    {
        $d = json_decode($json, true);
        if (! is_array($d)) {
            throw new RuntimeException(__('Die Datei ist kein gültiges JSON.'));
        }
        $fehler = self::pruefen($d);
        if ($fehler !== []) {
            throw new RuntimeException(implode("\n", $fehler));
        }

        return $d;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<string> Fehler; leer = gültig
     */
    public static function pruefen(array $d): array
    {
        $fehler = [];
        if (($d['format'] ?? null) !== self::FORMAT) {
            $fehler[] = __('Die Datei ist keine Notenbaum-Vorlage.');
        }
        if ((int) ($d['version'] ?? 0) !== self::VERSION) {
            $fehler[] = __('Unbekannte Version der Vorlage.');
        }
        if (trim((string) ($d['name'] ?? '')) === '') {
            $fehler[] = __('Die Vorlage hat keinen Namen.');
        }
        if (! in_array($d['bezug'] ?? Baum::LEHRBERUF, [Baum::LEHRBERUF, Baum::BILDUNGSGANG], true)) {
            $fehler[] = __('Unbekannter Bezug der Vorlage.');
        }
        if (($d['bezug'] ?? null) === Baum::BILDUNGSGANG && ! in_array($d['track_typ'] ?? null, ['BMS', 'ABU'], true)) {
            $fehler[] = __('Eine Vorlage für einen Bildungsgang braucht den Track BMS oder ABU.');
        }
        if (! is_array($d['wurzel'] ?? null)) {
            return [...$fehler, __('Die Vorlage hat keinen Wurzelknoten.')];
        }

        $kategorien = DB::table('kategorien')->pluck('code')->all();
        $codes = [];
        $pruefe = function (array $k, string $pfad) use (&$pruefe, &$fehler, &$codes, $kategorien): void {
            $code = (string) ($k['code'] ?? '');
            $wo = $pfad !== '' ? $pfad.' › '.$code : $code;
            if (! preg_match('/^[a-z0-9_]{1,40}$/', $code)) {
                $fehler[] = __('Knoten «:wo»: Code nur aus a–z, 0–9 und _ (höchstens 40 Zeichen).', ['wo' => $wo]);
            }
            if (isset($codes[$code])) {
                $fehler[] = __('Code «:code» kommt mehrfach vor.', ['code' => $code]);
            }
            $codes[$code] = true;
            $typ = $k['typ'] ?? null;
            if (! in_array($typ, Knoten::TYPEN, true)) {
                $fehler[] = __('Knoten «:wo»: unbekannter Typ.', ['wo' => $wo]);
            }
            if (isset($k['gewicht']) && (! is_numeric($k['gewicht']) || $k['gewicht'] < 0)) {
                $fehler[] = __('Knoten «:wo»: Gewicht muss eine Zahl ab 0 sein.', ['wo' => $wo]);
            }
            if (isset($k['rundung']) && ! in_array((float) $k['rundung'], [0.1, 0.5, 1.0], true)) {
                $fehler[] = __('Knoten «:wo»: Rundung ist 0.1, 0.5 oder 1.', ['wo' => $wo]);
            }
            if (isset($k['fallnote']) && (! is_numeric($k['fallnote']) || $k['fallnote'] < 1 || $k['fallnote'] > 6)) {
                $fehler[] = __('Knoten «:wo»: Fallnote liegt zwischen 1 und 6.', ['wo' => $wo]);
            }
            if ($typ === Knoten::KATEGORIE && ! in_array($k['kategorie'] ?? null, $kategorien, true)) {
                $fehler[] = __('Knoten «:wo»: Kategorie «:kat» gibt es nicht.', ['wo' => $wo, 'kat' => (string) ($k['kategorie'] ?? '')]);
            }
            if (isset($k['elementtyp']) && ! in_array($k['elementtyp'], Knoten::ELEMENTTYPEN, true)) {
                $fehler[] = __('Knoten «:wo»: unbekannter Elementtyp.', ['wo' => $wo]);
            }
            if ($typ === Knoten::FAECHER) {
                if (! is_array($k['faecher'] ?? null) || $k['faecher'] === []) {
                    $fehler[] = __('Knoten «:wo»: mindestens ein Fach angeben.', ['wo' => $wo]);
                }
                foreach ($k['faecher'] ?? [] as $f) {
                    if (trim((string) ($f['name'] ?? '')) === '' || (isset($f['kategorie']) && ! in_array($f['kategorie'], $kategorien, true))) {
                        $fehler[] = __('Knoten «:wo»: Fach ohne Namen oder mit unbekannter Kategorie.', ['wo' => $wo]);
                    }
                }
            }
            if ($typ === Knoten::GRUPPE && (! is_array($k['kinder'] ?? null) || $k['kinder'] === [])) {
                $fehler[] = __('Gruppe «:wo» hat keine Unterknoten.', ['wo' => $wo]);
            }
            if ($typ !== Knoten::GRUPPE && ! empty($k['kinder'])) {
                $fehler[] = __('Nur Gruppen haben Unterknoten («:wo»).', ['wo' => $wo]);
            }
            foreach ($k['kinder'] ?? [] as $kind) {
                is_array($kind) ? $pruefe($kind, $wo) : $fehler[] = __('Knoten «:wo»: ungültiger Unterknoten.', ['wo' => $wo]);
            }
        };
        $pruefe($d['wurzel'], '');

        return array_values(array_unique($fehler));
    }

    /**
     * Baum aus der Vorlage anlegen und aktivieren; ein bisher aktiver Baum desselben Lehrberufs bzw.
     * Bildungsgangs wird deaktiviert (nicht gelöscht). Fehlende Fächer werden angelegt und freigegeben.
     *
     * @param  array<string, mixed>  $d  geprüfte Vorlage
     * @return int neue baum_id
     */
    public function importieren(array $d, ?int $lehrberufId = null, ?string $vorlage = null): int
    {
        $bezug = $d['bezug'] ?? Baum::LEHRBERUF;
        if ($bezug === Baum::LEHRBERUF && $lehrberufId === null) {
            throw new RuntimeException(__('Für diese Vorlage einen Lehrberuf wählen.'));
        }
        $track = $bezug === Baum::BILDUNGSGANG ? (string) $d['track_typ'] : null;

        $id = DB::transaction(function () use ($d, $bezug, $lehrberufId, $track, $vorlage) {
            $kategorien = DB::table('kategorien')->pluck('kategorie_id', 'code')->map(fn ($v) => (int) $v)->all();

            DB::table('notenbaeume')->where('aktiv', true)
                ->when($bezug === Baum::LEHRBERUF, fn ($q) => $q->where('bezug', Baum::LEHRBERUF)->where('lehrberuf_id', $lehrberufId))
                ->when($bezug === Baum::BILDUNGSGANG, fn ($q) => $q->where('bezug', Baum::BILDUNGSGANG)->where('track_typ', $track))
                ->update(['aktiv' => false, 'aktualisiert_am' => now()]);

            $baumId = (int) DB::table('notenbaeume')->insertGetId([
                'name' => (string) $d['name'],
                'bezug' => $bezug,
                'lehrberuf_id' => $bezug === Baum::LEHRBERUF ? $lehrberufId : null,
                'track_typ' => $track,
                'vorlage' => $vorlage,
                'beschreibung' => isset($d['beschreibung']) ? mb_substr((string) $d['beschreibung'], 0, 500) : null,
                'aktiv' => true,
            ]);

            $this->knotenAnlegen($d['wurzel'], $baumId, null, 0, $kategorien, $lehrberufId, $track);

            return $baumId;
        });
        Konfiguration::vergessen();

        return $id;
    }

    /**
     * @param  array<string, mixed>  $k
     * @param  array<string, int>  $kategorien
     */
    private function knotenAnlegen(array $k, int $baumId, ?int $eltern, int $sortierung, array $kategorien, ?int $lehrberufId, ?string $track): void
    {
        $id = (int) DB::table('notenbaum_knoten')->insertGetId([
            'baum_id' => $baumId,
            'eltern_id' => $eltern,
            'code' => (string) $k['code'],
            'name' => mb_substr((string) ($k['name'] ?? $k['code']), 0, 150),
            'typ' => (string) $k['typ'],
            'gewicht' => (float) ($k['gewicht'] ?? 1),
            'rundung' => isset($k['rundung']) ? (float) $k['rundung'] : null,
            'fallnote' => isset($k['fallnote']) ? (float) $k['fallnote'] : null,
            'max_ungenuegend' => isset($k['max_ungenuegend']) ? (int) $k['max_ungenuegend'] : null,
            'max_minuspunkte' => isset($k['max_minuspunkte']) ? (float) $k['max_minuspunkte'] : null,
            'zaehlt' => (bool) ($k['zaehlt'] ?? true),
            'kategorie_id' => isset($k['kategorie']) && $k['typ'] === Knoten::KATEGORIE ? $kategorien[$k['kategorie']] : null,
            'elementtyp' => (string) ($k['elementtyp'] ?? Knoten::ALLE),
            'sortierung' => $sortierung,
        ]);

        foreach ($k['faecher'] ?? [] as $f) {
            DB::table('notenbaum_knoten_faecher')->insertOrIgnore([
                'knoten_id' => $id,
                'fach_id' => $this->fach($f, $kategorien, $lehrberufId, $track),
            ]);
        }

        foreach (array_values($k['kinder'] ?? []) as $i => $kind) {
            $this->knotenAnlegen($kind, $baumId, $id, $i, $kategorien, $lehrberufId, $track);
        }
    }

    /**
     * Fach nach Namen finden oder anlegen und freigeben: ohne Track über den Lehrberuf, sonst über den Track.
     * Gesucht wird nur im selben Track – BM-Englisch ist ein anderes Fach als Englisch der Berufsfachschule.
     *
     * @param  array<string, mixed>  $f
     * @param  array<string, int>  $kategorien
     */
    private function fach(array $f, array $kategorien, ?int $lehrberufId, ?string $track): int
    {
        $name = trim((string) $f['name']);
        $bestehend = DB::table('faecher')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where(fn ($q) => $track === null ? $q->whereNull('track_typ') : $q->where('track_typ', $track))
            ->orderBy('fach_id')->first();

        if ($bestehend) {
            $fachId = (int) $bestehend->fach_id;
        } else {
            $kurz = $this->freiesKuerzel((string) ($f['kurzname'] ?? mb_substr($name, 0, 4)), $track);
            $fachId = (int) DB::table('faecher')->insertGetId([
                'name' => $name,
                'kurzname' => $kurz,
                'kategorie_id' => $kategorien[$f['kategorie'] ?? ($track ?? 'FACH')] ?? $kategorien['FACH'] ?? null,
                'track_typ' => $track,
                'skala' => ($f['skala'] ?? 'note') === 'stufe' ? 'stufe' : 'note',
                'zaehlt' => (bool) ($f['zaehlt'] ?? true),
                'aktiv' => true,
                'erstellt_am' => now(),
                'aktualisiert_am' => now(),
            ]);
        }

        if ($track === null && $lehrberufId !== null) {
            DB::table('lehrberuf_faecher')->insertOrIgnore(['lehrberuf_id' => $lehrberufId, 'fach_id' => $fachId]);
        }

        return $fachId;
    }

    private function freiesKuerzel(string $wunsch, ?string $track): string
    {
        $basis = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $wunsch) ?: 'FACH', 0, 8));
        $kandidat = $basis;
        for ($i = 2; DB::table('faecher')->where('kurzname', $kandidat)
            ->where(fn ($q) => $track === null ? $q->whereNull('track_typ') : $q->where('track_typ', $track))->exists(); $i++) {
            $kandidat = $basis.$i;
        }

        return $kandidat;
    }

    /**
     * Baum als Vorlage (gleiches Format wie die mitgelieferten Dateien).
     *
     * @return array<string, mixed>
     */
    public function exportieren(int $baumId): array
    {
        $baum = DB::table('notenbaeume')->where('baum_id', $baumId)->first() ?? throw new RuntimeException(__('Notenbaum nicht gefunden.'));
        $knoten = DB::table('notenbaum_knoten')->where('baum_id', $baumId)->orderBy('sortierung')->orderBy('knoten_id')->get();
        $kategorien = DB::table('kategorien')->pluck('code', 'kategorie_id')->all();
        $faecher = DB::table('notenbaum_knoten_faecher as kf')->join('faecher as f', 'f.fach_id', '=', 'kf.fach_id')
            ->whereIn('kf.knoten_id', $knoten->pluck('knoten_id'))
            ->get(['kf.knoten_id', 'f.name', 'f.kurzname', 'f.kategorie_id', 'f.skala', 'f.zaehlt'])->groupBy('knoten_id');

        $nachEltern = $knoten->groupBy(fn ($k) => $k->eltern_id ?? 0);
        $bauen = function (object $k) use (&$bauen, $nachEltern, $kategorien, $faecher): array {
            $d = ['code' => $k->code, 'name' => $k->name, 'typ' => $k->typ];
            if ((float) $k->gewicht !== 1.0) {
                $d['gewicht'] = (float) $k->gewicht;
            }
            foreach (['rundung', 'fallnote', 'max_minuspunkte'] as $f) {
                if ($k->{$f} !== null) {
                    $d[$f] = (float) $k->{$f};
                }
            }
            if ($k->max_ungenuegend !== null) {
                $d['max_ungenuegend'] = (int) $k->max_ungenuegend;
            }
            if (! $k->zaehlt) {
                $d['zaehlt'] = false;
            }
            if ($k->typ === Knoten::KATEGORIE) {
                $d['kategorie'] = $kategorien[$k->kategorie_id] ?? null;
            }
            if ($k->elementtyp !== Knoten::ALLE) {
                $d['elementtyp'] = $k->elementtyp;
            }
            if ($k->typ === Knoten::FAECHER) {
                $d['faecher'] = ($faecher[$k->knoten_id] ?? collect())->map(fn ($f) => array_filter([
                    'name' => $f->name, 'kurzname' => $f->kurzname, 'kategorie' => $kategorien[$f->kategorie_id] ?? null,
                    'skala' => $f->skala !== 'note' ? $f->skala : null, 'zaehlt' => $f->zaehlt ? null : false,
                ], fn ($v) => $v !== null))->values()->all();
            }
            $kinder = $nachEltern[$k->knoten_id] ?? collect();
            if ($kinder->isNotEmpty()) {
                $d['kinder'] = $kinder->map(fn ($kind) => $bauen($kind))->values()->all();
            }

            return $d;
        };

        $wurzel = ($nachEltern[0] ?? collect())->first();

        return array_filter([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'name' => $baum->name,
            'beschreibung' => $baum->beschreibung,
            'bezug' => $baum->bezug,
            'track_typ' => $baum->track_typ,
            'wurzel' => $wurzel ? $bauen($wurzel) : null,
        ], fn ($v) => $v !== null);
    }
}
