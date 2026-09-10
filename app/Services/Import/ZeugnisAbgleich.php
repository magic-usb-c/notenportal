<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Services\Auswertung\NotenQuelle;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;

/**
 * Zeugnis (PDF mit Textlayer) gegen die Zeugnisnoten des Rechenkerns prüfen:
 * gleich / abweichend / fehlt im Portal. Fehlende lassen sich als Note «Zeugnis» übernehmen.
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
     * Zeilen «Fach …… Note» erkennen; bei mehreren Noten pro Zeile zählt die letzte (aktuelles Semester).
     *
     * @return list<array{label: string, name: string, note: float, bezug: string, sicher: bool}>
     */
    public function zeilen(string $text, int $lernenderId): array
    {
        $katalog = $this->import->katalog($lernenderId);
        $labels = array_column($katalog, 'label', 'wert');
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $zeile) {
            $zeile = trim(preg_replace(['/[.·_…]{2,}/u', '/\s+/u'], ' ', $zeile) ?? '');
            if (! preg_match('/^(?<name>.*?\p{L}.*?)\s+(?<noten>(?:[1-6](?:[.,]\d{1,2})?\s*)+)$/u', $zeile, $m)) {
                continue;
            }
            $noten = preg_split('/\s+/', trim($m['noten'])) ?: [];
            $note = $this->import->note((string) end($noten));
            [$bezug, $sicher] = $this->import->bezug($m['name'], $katalog);
            if ($note === null || $bezug === null || isset($out[$bezug])) {
                continue;
            }
            $out[$bezug] = ['label' => trim($m['name']), 'name' => $labels[$bezug] ?? $m['name'], 'note' => $note, 'bezug' => $bezug, 'sicher' => $sicher];
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
