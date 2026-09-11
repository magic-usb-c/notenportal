<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Services\Auswertung\NotenQuelle;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;

/**
 * Zeugnis (PDF mit Textlayer) gegen die Zeugnisnoten des Rechenkerns prüfen:
 * gleich / abweichend / fehlt im Portal; Fächer ohne Gegenstück im Portal erscheinen als «unbekannt».
 * Fehlende lassen sich als Note «Zeugnis» übernehmen.
 */
final class ZeugnisAbgleich
{
    public function __construct(
        private readonly NotenImport $import,
        private readonly NotenQuelle $quelle,
    ) {}

    /** @return array{text: bool, zeilen: list<array<string, mixed>>} */
    public function pruefen(string $pfad, int $lernenderId, ?int $semesterId): array
    {
        try {
            $text = (new Parser)->parseFile($pfad)->getText();
        } catch (\Throwable) {
            $text = '';
        }

        $a = $this->quelle->auswertung($lernenderId);
        $zeilen = [];
        foreach ($this->zeilen($text, $lernenderId) as $z) {
            if ($z['bezug'] === null) {
                $zeilen[] = [...$z, 'portal' => null, 'differenz' => null, 'status' => 'unbekannt'];

                continue;
            }
            [$typ, $id] = explode(':', $z['bezug']);
            $element = $typ === 'fach'
                ? ($semesterId ? ($a->elemente["f{$id}s{$semesterId}"] ?? null) : null)
                : ($a->elemente["m{$id}"] ?? null);
            $portal = $element?->note;
            $differenz = $portal !== null ? round($z['note'] - $portal, 2) : null;
            $zeilen[] = [...$z, 'portal' => $portal, 'differenz' => $differenz,
                'status' => $portal === null ? 'fehlt' : (abs((float) $differenz) < 0.001 ? 'gleich' : 'abweichung')];
        }

        return ['text' => trim($text) !== '', 'zeilen' => $zeilen];
    }

    /**
     * Fächer und Module mit der Note des aktuellen Semesters; im BM-Zeugnis nur BM-Fächer, sonst Berufsfachschule vor BM.
     *
     * @return list<array{label: string, name: string, note: float, bezug: ?string, sicher: bool}>
     */
    public function zeilen(string $text, int $lernenderId): array
    {
        $art = (new Schulnetz)->art($text);
        if (in_array($art, ['aktuell', 'stammdaten'], true)) {
            return [];
        }

        $katalog = $this->import->katalog($lernenderId);
        $labels = array_column($katalog, 'label', 'wert');
        $auswahl = ['bm' => $this->import->bevorzugt($katalog, true), 'bfs' => $this->import->bevorzugt($katalog, false)];
        $out = [];
        foreach ((new ZeugnisText)->zeilen($text, $art !== 'zeugnis') as $z) {
            [$bezug, $sicher] = $this->import->bezug($z['name'], $auswahl[$z['bm'] ? 'bm' : 'bfs']);
            $schluessel = $bezug ?? '?'.ZeugnisText::kompakt($z['name']);
            if (isset($out[$schluessel])) {
                continue;
            }
            $out[$schluessel] = ['label' => $z['name'], 'name' => $bezug !== null ? ($labels[$bezug] ?? $z['name']) : $z['name'],
                'note' => $z['note'], 'bezug' => $bezug, 'sicher' => $bezug !== null && $sicher];
        }

        return array_values($out);
    }

    /**
     * Markierte Zeugnisnoten als Note «Zeugnis» (Gewicht 100) am Semesterende eintragen.
     *
     * @param  array<int, array{bezug: string, note: numeric, uebernehmen?: bool|string|null}>  $zeilen
     * @return array{neu: int, fehler: list<string>}
     */
    public function uebernehmen(array $zeilen, int $semesterId, int $lernenderId, int $benutzerId): array
    {
        $semester = DB::table('semester')->where('semester_id', $semesterId)->first(['start_datum', 'end_datum']);
        $lehrende = DB::table('lernende')->where('lernender_id', $lernenderId)->value('lehrende');
        $datum = $lehrende && (string) $lehrende < (string) $semester->end_datum ? max((string) $lehrende, (string) $semester->start_datum) : (string) $semester->end_datum;

        $markiert = [];
        foreach (array_values($zeilen) as $i => $z) {
            if (! empty($z['uebernehmen'])) {
                $markiert[] = ['nr' => $i + 1, 'datum' => substr($datum, 0, 10), 'bezug' => $z['bezug'], 'titel' => 'Zeugnis',
                    'note' => (float) $z['note'], 'gewicht' => 100, 'uebernehmen' => true];
            }
        }

        return $this->import->importieren($markiert, $lernenderId, $benutzerId);
    }
}
