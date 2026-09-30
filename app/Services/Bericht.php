<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lernender;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Lernstand;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Auswertung\Rundung;
use App\Support\NotenSkala;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Notenbericht über den ganzen Betrieb auf Basis der Zeugnisnoten: wer steht wo, welche Kategorien und
 * Fächer/Module ziehen nach unten. Ohne Semester gilt die ganze Lehrzeit.
 */
final class Bericht
{
    public const array SORTIERUNGEN = ['status', 'name', 'gesamt', 'semester', 'ungenuegend', 'pruefungen', 'letzte'];

    public function __construct(private readonly LernstandRechner $lernstaende) {}

    /**
     * @param  array{semester_id: ?int, lehrberuf_id: ?int, berufsbildner_id: ?int}  $filter
     * @return array{zeilen: Collection, kennzahlen: array<string, mixed>, verteilung: array<string, mixed>, kategorien: list<array<string, mixed>>, schwachstellen: list<array<string, mixed>>, nachLehrjahr: list<array<string, mixed>>}
     */
    public function noten(array $filter, string $sort = 'status', string $dir = 'asc'): array
    {
        $sid = $filter['semester_id'];
        $lernende = $this->lernende($filter['lehrberuf_id'], $filter['berufsbildner_id']);
        $ids = $lernende->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();
        $staende = $this->lernstaende->fuer($ids);
        $grenze = NotenSkala::genuegend();

        $pruefungen = DB::table('noten')
            ->whereIn('lernender_id', $ids)
            ->whereNull('geloescht_am')
            ->when($sid, fn ($q) => $q->where('semester_id', $sid))
            ->groupBy('lernender_id')
            ->selectRaw('lernender_id, COUNT(*) as anzahl, MAX(pruefungsdatum) as letzte')
            ->get()
            ->keyBy('lernender_id');

        $zeilen = $lernende->map(function ($l) use ($staende, $sid, $pruefungen, $grenze) {
            $stand = $staende[(int) $l->lernender_id];
            $p = $pruefungen->get($l->lernender_id);

            return (object) [
                'id' => (int) $l->lernender_id,
                'vorname' => $l->vorname,
                'nachname' => $l->nachname,
                'lehrberuf' => $l->kuerzel ?: $l->lehrberuf,
                'stand' => $stand,
                'gesamt' => $stand->auswertung->gesamtNote,
                'lehrjahr' => (new Lernender(['lehrbeginn' => $l->lehrbeginn]))->lehrjahr(),
                'semester' => $sid ? $stand->auswertung->semester($sid)['note'] : null,
                'ungenuegend' => count(array_filter($this->zeugnisnoten($stand->auswertung, $sid), fn (Element $e) => $e->note < $grenze - 1e-9)),
                'pruefungen' => (int) ($p?->anzahl ?? 0),
                'letzte' => $p?->letzte,
            ];
        });

        return [
            'zeilen' => $this->sortiere($zeilen, $sort, $dir),
            ...$this->aggregate($zeilen, $sid, $grenze),
        ];
    }

    /** @return list<Element> zählende Zeugnisnoten eines Semesters oder der ganzen Lehrzeit (IDAF, Sport gehen nicht ein) */
    private function zeugnisnoten(Auswertung $a, ?int $sid): array
    {
        return array_values(array_filter($a->elemente, fn (Element $e) => $e->zaehlt && $e->note !== null && ($sid === null || $e->semesterId === $sid)));
    }

    /** @return array{kennzahlen: array<string, mixed>, verteilung: array<string, mixed>, kategorien: list<array<string, mixed>>, schwachstellen: list<array<string, mixed>>} */
    private function aggregate(Collection $zeilen, ?int $sid, float $grenze): array
    {
        $k = Konfiguration::ausDb();
        $kat = [];
        $schwach = [];
        $alle = [];
        $gefaehrdet = 0;

        foreach ($zeilen as $z) {
            $a = $z->stand->auswertung;
            $istGefaehrdet = false;
            foreach ($a->kategorien as $kid => $kategorie) {
                $note = $sid ? $a->semester($sid, $kid)['note'] : $kategorie['note'];
                if ($note === null) {
                    continue;
                }
                $kat[$kid]['noten'][] = $note;
                $promotion = $sid ? $a->promotion($kid, $sid) : null;
                if ($promotion !== null && ! $promotion['erfuellt']) {
                    $kat[$kid]['gefaehrdet'] = ($kat[$kid]['gefaehrdet'] ?? 0) + 1;
                    $istGefaehrdet = true;
                }
            }
            $gefaehrdet += (int) $istGefaehrdet;

            foreach ($this->zeugnisnoten($a, $sid) as $e) {
                $alle[] = $e->note;
                $ungenuegend = (int) ($e->note < $grenze - 1e-9);
                $kat[$e->kategorieId]['ungenuegend'] = ($kat[$e->kategorieId]['ungenuegend'] ?? 0) + $ungenuegend;
                $schluessel = $e->typ === Element::FACH ? 'f'.$e->fachId : 'm'.$e->modulId;
                $schwach[$schluessel] ??= ['label' => $e->typ === Element::FACH ? $k->fachName((int) $e->fachId) : $k->modulName((int) $e->modulId),
                    'kategorie' => $k->kategorieName($e->kategorieId), 'noten' => [], 'ungenuegend' => 0];
                $schwach[$schluessel]['noten'][] = $e->note;
                $schwach[$schluessel]['ungenuegend'] += $ungenuegend;
            }
        }

        $kategorien = [];
        foreach (array_keys($k->kategorien) as $kid) {
            if (! isset($kat[$kid]['noten'])) {
                continue;
            }
            $noten = $kat[$kid]['noten'];
            $kategorien[] = [
                'name' => $k->kategorieName($kid),
                'schnitt' => Rundung::mittel($noten),
                'anzahl' => count($noten),
                'min' => min($noten),
                'max' => max($noten),
                'ungenuegend' => $kat[$kid]['ungenuegend'] ?? 0,
                'gefaehrdet' => $kat[$kid]['gefaehrdet'] ?? 0,
            ];
        }

        $schwachstellen = collect($schwach)
            ->map(fn ($s) => [...$s, 'schnitt' => Rundung::mittel($s['noten']), 'anzahl' => count($s['noten'])])
            ->sortBy([['schnitt', 'asc'], ['ungenuegend', 'desc']])
            ->take(8)
            ->values()
            ->all();

        $buckets = [];
        for ($b = 10; $b <= 60; $b += 5) {
            $buckets[number_format($b / 10, 1)] = 0;
        }
        // Klasse = Untergrenze [x, x+0.5): so färbt das Diagramm (charts.js histogramm). Mit round() fiel 3.8
        // (Rundung 0.1) in die Klasse 4.0 und stand im genügenden Bereich. 1e-9 fängt 4.0 als 3.9999… ab.
        foreach ($alle as $note) {
            $buckets[number_format(floor($note * 2 + 1e-9) / 2, 1)]++;
        }

        $gesamt = $zeilen->pluck('gesamt')->filter(fn ($v) => $v !== null)->values()->all();

        return [
            'kennzahlen' => [
                'lernende' => $zeilen->count(),
                'schnitt' => Rundung::mittel($gesamt),
                'rot' => $zeilen->filter(fn ($z) => $z->stand->status === Lernstand::ROT)->count(),
                'gelb' => $zeilen->filter(fn ($z) => $z->stand->status === Lernstand::GELB)->count(),
                'ungenuegend' => $zeilen->sum('ungenuegend'),
                'gefaehrdet' => $gefaehrdet,
                'zeugnisnoten' => count($alle),
            ],
            'verteilung' => [
                'labels' => array_keys($buckets),
                'werte' => array_values($buckets),
                'farben' => array_map('floatval', array_keys($buckets)),
                'grenzen' => NotenSkala::grenzen(),
                'name' => 'Zeugnisnoten',
            ],
            'kategorien' => $kategorien,
            'schwachstellen' => $schwachstellen,
            'nachLehrjahr' => $this->nachLehrjahr($zeilen),
        ];
    }

    /** Gesamtschnitt je Lehrjahr, mit der Zahl der Lernenden, die in den Schnitt einfliessen. */
    private function nachLehrjahr(Collection $zeilen): array
    {
        return $zeilen
            ->filter(fn ($z) => $z->lehrjahr !== null && $z->gesamt !== null)
            ->groupBy('lehrjahr')
            ->sortKeys()
            ->map(fn (Collection $gruppe, int $jahr) => [
                'jahr' => $jahr,
                'schnitt' => Rundung::mittel($gruppe->pluck('gesamt')->all()),
                'anzahl' => $gruppe->count(),
            ])
            ->values()
            ->all();
    }

    private function sortiere(Collection $zeilen, string $sort, string $dir): Collection
    {
        $wert = fn ($z) => match ($sort) {
            'status' => [$z->stand->rang(), mb_strtolower($z->nachname.' '.$z->vorname)],
            'gesamt' => $z->gesamt ?? -1,
            'semester' => $z->semester ?? -1,
            'ungenuegend' => $z->ungenuegend,
            'pruefungen' => $z->pruefungen,
            'letzte' => $z->letzte ?? '',
            default => mb_strtolower($z->nachname.' '.$z->vorname),
        };

        return ($dir === 'desc' ? $zeilen->sortByDesc($wert) : $zeilen->sortBy($wert))->values();
    }

    private function lernende(?int $lehrberufId, ?int $berufsbildnerId): Collection
    {
        $heute = now()->toDateString();

        return DB::table('lernende as l')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'l.benutzer_id')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->whereNull('l.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->when($lehrberufId, fn ($q) => $q->where('l.lehrberuf_id', $lehrberufId))
            ->when($berufsbildnerId, fn ($q) => $q->whereIn('l.lernender_id', fn ($sub) => $sub->select('lernender_id')
                ->from('betreuungen')
                ->where('berufsbildner_id', $berufsbildnerId)
                ->where('gueltig_von', '<=', $heute)
                ->where(fn ($w) => $w->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $heute))))
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get(['l.lernender_id', 'l.lehrbeginn', 'b.vorname', 'b.nachname', 'lb.name as lehrberuf', 'lb.kuerzel']);
    }
}
