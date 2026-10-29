<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lernender;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Leistung;
use App\Services\Auswertung\Lernstand;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechenkern;
use App\Services\Auswertung\Statistik;
use App\Services\Auswertung\Verteilung;
use App\Services\Auswertung\Zielgroesse;
use App\Services\Auswertung\Zielrechner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Schneidet die Ergebnisse von Auswertung, Statistik und Uebersicht für die Statistikseiten zu (Katalog S1–S11,
 * docs/auftrag/GUI-R6.md §6): Diagrammdaten, Tabelle, Zusammenfassung in einem Satz und Meta. Gerechnet wird hier
 * nichts – Schnitte, Median und Quartile kommen aus Auswertung, Statistik und Verteilung.
 *
 * Jeder Baustein liefert einen Teil {schluessel, diagramm, tabelle, zusammenfassung, meta}; StatistikAntwort::paket
 * setzt die Teile einer Seite zusammen.
 */
final class StatistikDaten
{
    /** Standardwerte der Filter der Lernenden-Statistiken (Verlauf S1, Wo stehe ich S2). */
    public const array STANDARD_LERNENDER = ['zeitraum' => 'alles', 'achse' => 'datum', 'modus' => 'semester'];

    public function __construct(
        private readonly NotenQuelle $quelle,
        private readonly Statistik $statistik = new Statistik,
        private readonly Rechenkern $kern = new Rechenkern,
    ) {}

    /**
     * Leistungen, Regeln und Auswertung eines Lernenden – einmal geladen, von mehreren Bausteinen geteilt.
     *
     * @return array{leistungen: list<Leistung>, konfiguration: Konfiguration, auswertung: Auswertung}
     */
    public function lernender(int $lernenderId, bool $mitGeplanten = false): array
    {
        $leistungen = $this->quelle->fuerLernenden($lernenderId, $mitGeplanten);
        $k = $this->quelle->konfiguration($lernenderId);
        $a = $this->kern->auswerten($leistungen, $k);
        $a->lernenderId = $lernenderId;

        return ['leistungen' => $leistungen, 'konfiguration' => $k, 'auswertung' => $a];
    }

    /**
     * Regeln für StatistikFilter: Verlauf (zeitraum, kategorie, fach, achse) und Wo stehe ich (modus, kategorie).
     * Kategorien und Fächer nur aus dem, was dieser Lernende hat.
     *
     * @return array<string, list<int|string>|array{id: list<int>}>
     */
    public function regelnLernender(Auswertung $a): array
    {
        $faecher = [];
        foreach ($a->elemente as $e) {
            if ($e->typ === Element::FACH && $e->fachId !== null) {
                $faecher[$e->fachId] = $e->fachId;
            }
        }

        return [
            'zeitraum' => ['semester', 'lehrjahr', 'alles'],
            'kategorie' => ['id' => array_map('intval', array_keys($a->kategorien))],
            'fach' => ['id' => array_values($faecher)],
            'achse' => ['datum', 'semester'],
            'modus' => ['semester', 'lehrzeit'],
        ];
    }

    /** Beschriftungen der Filterwerte; die Seiten setzen daraus ihre Auswahlfelder. */
    public function optionen(): array
    {
        return [
            'zeitraum' => ['semester' => __('Dieses Semester'), 'lehrjahr' => __('Dieses Lehrjahr'), 'alles' => __('Gesamte Lehrzeit')],
            'achse' => ['datum' => __('Datum'), 'semester' => __('Semester')],
            'modus' => ['semester' => __('Semester'), 'lehrzeit' => __('Lehrzeit')],
            'sort' => ['delta' => __('Veränderung'), 'fach' => __('Fach'), 'name' => __('Name')],
            'pruefungen' => ['4w' => __('4 Wochen'), '8w' => __('8 Wochen'), 'alle' => __('Alle')],
            'erfassung' => ['12w' => __('12 Wochen'), '26w' => __('26 Wochen'), '52w' => __('52 Wochen')],
            'status' => ['alle' => __('Alle'), 'kritisch' => __('Kritisch')],
        ];
    }

    // ── S1 Verlauf ───────────────────────────────────────────────────────────────────────────────────────

    /**
     * Notenverlauf Prüfung für Prüfung: laufender Schnitt an Monatsenden (achse=datum) oder Semesternoten
     * (achse=semester), dazu die Prüfungspunkte als Tabelle und Positionen ohne Datum als Liste.
     *
     * @param  list<Leistung>  $leistungen
     */
    public function verlauf(StatistikFilter $f, array $leistungen, Auswertung $a, ?Lernender $lernender = null): array
    {
        $k = $a->konfiguration;
        $fach = $f->wert('fach');
        $kat = $f->wert('kategorie');
        $ziel = match (true) {
            $fach !== null => new Zielgroesse('fach', (int) $fach),
            $kat !== null => new Zielgroesse('kategorie', (int) $kat),
            default => new Zielgroesse('gesamt'),
        };
        $name = match (true) {
            $fach !== null => $k->fachName((int) $fach),
            $kat !== null => $k->kategorieName((int) $kat),
            default => __('Gesamtschnitt'),
        };

        [$von, $bis] = $this->zeitfenster((string) $f->wert('zeitraum'), $leistungen, $k, $lernender);
        $r = $this->statistik->stichtagsreihe($leistungen, $k, $ziel, $von, $bis);

        // Positionen ohne Datum (IPA, Schlussprüfung) gehören zur Gesamtnote, nicht zu einem Fach
        $namen = $this->knotenNamen($k);
        $ohneDatum = $ziel->ebene === 'gesamt'
            ? array_map(fn (array $p) => ['titel' => $p['titel'] ?? ($namen[$p['knotenId']] ?? '–'), 'wert' => $p['wert']], $r['meta']['ohneDatum'])
            : [];

        $nebenreihen = [];
        if ($f->wert('achse') === 'semester') {
            $ids = $a->semesterIds();
            $labels = array_map(fn (int $s) => $k->semesterName($s, $a->lernenderId), $ids);
            $werte = array_map(fn (int $s) => match (true) {
                $fach !== null => ($a->elemente["f{$fach}s{$s}"] ?? null)?->note,
                $kat !== null => $a->semester($s, (int) $kat)['note'],
                default => $a->semester($s)['note'],
            }, $ids);
            // ohne Kategorie und Fach: Gesamtschnitt hervorgehoben, dazu je Kategorie eine gedämpfte Reihe (Vergleich wie im alten Dashboard)
            if ($fach === null && $kat === null) {
                foreach (array_keys($a->kategorien) as $kid) {
                    $reihe = array_map(fn (int $s) => $a->semester($s, $kid)['note'], $ids);
                    if (array_filter($reihe, fn ($w) => $w !== null) !== []) {
                        $nebenreihen[] = ['name' => $k->kategorieName($kid), 'werte' => $reihe];
                    }
                }
            }
            $spalten = [__('Semester'), $nebenreihen === [] ? __('Note') : $name, ...array_column($nebenreihen, 'name')];
            $zeilen = [];
            foreach ($labels as $i => $label) {
                $zeilen[] = [$label, NotenSkala::format($werte[$i], 1), ...array_map(fn (array $n) => NotenSkala::format($n['werte'][$i], 1), $nebenreihen)];
            }
            $tabelle = ['spalten' => $spalten, 'zeilen' => $zeilen];
            $kennwerte = array_values(array_filter($werte, fn ($w) => $w !== null));
            $satz = $kennwerte === []
                ? __('Noch keine Noten im Zeitraum.')
                : __('Schnitt von :von auf :bis über :n Semester.', ['von' => NotenSkala::format($kennwerte[0], 1), 'bis' => NotenSkala::format(end($kennwerte), 1), 'n' => count($kennwerte)]);
        } else {
            $labels = [];
            foreach ($r['reihe'] as $i => $p) {
                $tag = Carbon::parse($p['stichtag']);
                $labels[] = $i === count($r['reihe']) - 1 && ! $tag->isLastOfMonth() ? $tag->format('d.m.Y') : $tag->format('m.Y');
            }
            $werte = array_column($r['reihe'], 'wert');
            $tabelle = ['spalten' => [__('Datum'), __('Fach'), __('Note')], 'zeilen' => array_map(
                fn (array $p) => [Carbon::parse($p['datum'])->format('d.m.Y'), $p['fach'], NotenSkala::format($p['note'], 2)],
                $r['punkte'],
            )];
            $kennwerte = array_values(array_filter($werte, fn ($w) => $w !== null));
            $satz = $kennwerte === []
                ? __('Noch keine Noten im Zeitraum.')
                : __('Schnitt von :von auf :bis, :n Prüfungen im Zeitraum.', ['von' => NotenSkala::format($kennwerte[0], 1), 'bis' => NotenSkala::format(end($kennwerte), 1), 'n' => count($r['punkte'])]);
        }

        return [
            'schluessel' => 'verlauf',
            'diagramm' => [
                'achse' => (string) $f->wert('achse'),
                'labels' => $labels,
                'serien' => [['name' => $name, 'werte' => array_values($werte), 'dick' => true], ...$nebenreihen],
                'grenze' => $k->genuegend,
                'punkte' => $r['punkte'],
            ],
            'tabelle' => $tabelle,
            'zusammenfassung' => $satz,
            'meta' => [
                'titel' => __('Notenverlauf'),
                'vergroebert' => $r['meta']['vergroebert'],
                'von' => $r['meta']['von'],
                'bis' => $r['meta']['bis'],
                'ohneDatum' => $ohneDatum,
                'optionen' => ['zeitraum' => $this->optionen()['zeitraum'], 'achse' => $this->optionen()['achse']],
            ],
        ];
    }

    /**
     * @param  list<Leistung>  $leistungen
     * @return array{0: Carbon, 1: Carbon}
     */
    private function zeitfenster(string $zeitraum, array $leistungen, Konfiguration $k, ?Lernender $lernender): array
    {
        $heute = Carbon::today();
        $von = null;

        if ($zeitraum === 'semester') {
            $sid = $k->semesterFuerDatum($heute->toDateString());
            $von = $sid !== null ? Carbon::parse($k->semester[$sid]['start']) : null;
        } elseif ($zeitraum === 'lehrjahr' && $lernender?->lehrbeginn !== null) {
            $jahre = intdiv((int) $lernender->lehrbeginn->diffInMonths($heute), 12);
            $von = $lernender->lehrbeginn->copy()->addYears($jahre);
        } elseif ($zeitraum === 'alles') {
            $daten = array_values(array_filter(array_map(fn (Leistung $l) => $l->datum !== null ? substr($l->datum, 0, 10) : null, $leistungen)));
            $von = $daten !== [] ? Carbon::parse(min($daten)) : null;
        }

        $von ??= $heute->copy()->subYear();

        return [$von->gt($heute) ? $heute->copy() : $von->copy(), $heute];
    }

    /** @return array<int, string> Name je Position (Knoten) der Notenbäume dieses Lernenden */
    private function knotenNamen(Konfiguration $k): array
    {
        $namen = [];
        foreach ($k->baeume as $baum) {
            foreach ($baum->alle() as $knoten) {
                $namen[$knoten->id] = $knoten->name;
            }
        }

        return $namen;
    }

    // ── S2 Wo stehe ich ─────────────────────────────────────────────────────────────────────────────────

    /** Zeugnisnoten, schwächste zuerst: im laufenden Semester und über die Lehrzeit, optional nur eine Kategorie. */
    public function wostehe(StatistikFilter $f, Auswertung $a): array
    {
        $k = $a->konfiguration;
        $kat = $f->wert('kategorie') !== null ? (int) $f->wert('kategorie') : null;
        $ids = $a->semesterIds();
        $sid = $k->semesterFuerDatum(Carbon::today()->toDateString()) ?? ($ids === [] ? null : end($ids));

        $semester = $sid !== null ? array_values(array_filter($a->semester($sid, $kat)['elemente'], fn (Element $e) => $e->note !== null)) : [];
        usort($semester, fn (Element $x, Element $y) => $x->note <=> $y->note);

        $lehrzeit = [];
        foreach ($a->elemente as $e) {
            if ($kat !== null && $e->kategorieId !== $kat) {
                continue;
            }
            if ($e->typ === Element::MODUL && $e->note !== null) {
                $lehrzeit[$e->label] = $e->note;
            } elseif ($e->typ === Element::FACH && ! isset($lehrzeit[$e->label])) {
                $note = $a->fach((int) $e->fachId)['note'];
                if ($note !== null) {
                    $lehrzeit[$e->label] = $note;
                }
            }
        }
        asort($lehrzeit);

        $modus = $f->wert('modus') === 'lehrzeit' || $semester === [] ? 'lehrzeit' : 'semester';
        $labels = $modus === 'semester' ? array_map(fn (Element $e) => $e->label, $semester) : array_keys($lehrzeit);
        $werte = $modus === 'semester' ? array_map(fn (Element $e) => $e->note, $semester) : array_values($lehrzeit);

        $satz = __('Noch keine Zeugnisnoten.');
        if ($werte !== []) {
            $satz = $werte[0] < $k->genuegend - 1e-9
                ? __(':fach zieht den Schnitt am stärksten herunter (:note).', ['fach' => $labels[0], 'note' => NotenSkala::format($werte[0], 1)])
                : __('Alle Noten sind genügend, die schwächste ist :fach (:note).', ['fach' => $labels[0], 'note' => NotenSkala::format($werte[0], 1)]);
        }

        return [
            'schluessel' => 'wostehe',
            'diagramm' => [
                'modus' => $modus,
                'semester' => ['name' => $k->semesterName($sid, $a->lernenderId), 'labels' => array_map(fn (Element $e) => $e->label, $semester), 'werte' => array_map(fn (Element $e) => $e->note, $semester)],
                'lehrzeit' => ['labels' => array_keys($lehrzeit), 'werte' => array_values($lehrzeit)],
                'grenzen' => NotenSkala::grenzen(),
            ],
            'tabelle' => ['spalten' => [__('Fach'), __('Note'), __('Stufe')], 'zeilen' => array_map(
                fn ($l, $w) => [$l, NotenSkala::format($w, 1), NotenSkala::stufeName($w)],
                $labels,
                $werte,
            )],
            'zusammenfassung' => $satz,
            'meta' => ['titel' => __('Wo stehe ich'), 'optionen' => ['modus' => $this->optionen()['modus']]],
        ];
    }

    // ── S3 Hantel je Fach ───────────────────────────────────────────────────────────────────────────────

    /** Vorsemester gegen gewähltes Semester je Fach (Zeugnisnoten, Pfeil und Delta). */
    public function hantel(Auswertung $a, ?int $semesterId, ?int $kategorieId, string $sort): array
    {
        $k = $a->konfiguration;
        $ids = $a->semesterIds();
        $semesterId ??= $k->semesterFuerDatum(Carbon::today()->toDateString()) ?? ($ids === [] ? null : end($ids));

        $vorher = null;
        if ($semesterId !== null) {
            if (! in_array($semesterId, $ids, true)) {
                $ids[] = $semesterId;
            }
            usort($ids, fn (int $x, int $y) => $k->semesterSortierung($x) <=> $k->semesterSortierung($y));
            $stelle = array_search($semesterId, $ids, true);
            $vorher = $stelle > 0 ? $ids[$stelle - 1] : null;
        }

        $zeilen = $semesterId !== null ? $this->statistik->hanteln($a, $semesterId, $vorher, $kategorieId) : [];
        if ($sort === 'fach') {
            usort($zeilen, fn (array $x, array $y) => mb_strtolower($x['fach']) <=> mb_strtolower($y['fach']));
        }

        $besser = count(array_filter($zeilen, fn (array $z) => $z['delta'] !== null && $z['delta'] > 1e-9));
        $schlechter = count(array_filter($zeilen, fn (array $z) => $z['delta'] !== null && $z['delta'] < -1e-9));

        return [
            'schluessel' => 'hantel',
            'diagramm' => [
                'semester' => $k->semesterName($semesterId, $a->lernenderId),
                'vorsemester' => $vorher !== null ? $k->semesterName($vorher, $a->lernenderId) : null,
                'zeilen' => $zeilen,
                'grenze' => $k->genuegend,
            ],
            'tabelle' => $this->hantelTabelle($zeilen, __('Fach')),
            'zusammenfassung' => $vorher === null
                ? __('Kein Vorsemester zum Vergleich.')
                : __(':besser besser, :schlechter schlechter als im Vorsemester.', ['besser' => $besser, 'schlechter' => $schlechter]),
            'meta' => ['titel' => __('Vorsemester gegen aktuell'), 'optionen' => ['sort' => ['delta' => $this->optionen()['sort']['delta'], 'fach' => $this->optionen()['sort']['fach']]]],
        ];
    }

    /**
     * Hantel je betreute Person (Berufsbildner): Vorsemester → aktuelles Semester, mit Kategorie die Kategorienote
     * beider Semester aus der Auswertung der Person. Nur Personen aus $zeilen (Lernender::sichtbarFuer).
     *
     * @param  Collection<int, object>  $zeilen  Uebersicht::berufsbildner()['zeilen']
     */
    public function hantelPersonen(Collection $zeilen, ?int $kategorieId, string $status, string $sort): array
    {
        $rows = [];
        foreach ($zeilen as $z) {
            if ($status === 'kritisch' && $z->stand->status !== Lernstand::ROT) {
                continue;
            }
            $stand = $z->stand;
            $jetzt = $stand->semesterNote;
            $vorher = $stand->vorsemesterNote;
            if ($kategorieId !== null) {
                $a = $stand->auswertung;
                $ids = $a->semesterIds();
                $stelle = $stand->semesterId !== null ? array_search($stand->semesterId, $ids, true) : false;
                $jetzt = $stand->semesterId !== null ? $a->semester($stand->semesterId, $kategorieId)['note'] : null;
                $vorher = $stelle !== false && $stelle > 0 ? $a->semester($ids[$stelle - 1], $kategorieId)['note'] : null;
            }
            $rows[] = [
                'schluessel' => 'l'.$z->lernender->lernender_id,
                'fach' => $z->lernender->benutzer->vorname.' '.$z->lernender->benutzer->nachname,
                'kategorie_id' => $kategorieId,
                'vorher' => $vorher,
                'jetzt' => $jetzt,
                'delta' => Statistik::delta($jetzt, $vorher),
                'status' => $stand->status,
            ];
        }

        usort($rows, function (array $x, array $y) use ($sort) {
            if ($sort === 'name') {
                return mb_strtolower($x['fach']) <=> mb_strtolower($y['fach']);
            }
            if (($x['delta'] === null) !== ($y['delta'] === null)) {
                return $x['delta'] === null ? 1 : -1;
            }

            return [$x['delta'] ?? 0, mb_strtolower($x['fach'])] <=> [$y['delta'] ?? 0, mb_strtolower($y['fach'])];
        });

        $schlechter = count(array_filter($rows, fn (array $z) => $z['delta'] !== null && $z['delta'] < -1e-9));
        $besser = count(array_filter($rows, fn (array $z) => $z['delta'] !== null && $z['delta'] > 1e-9));

        return [
            'schluessel' => 'hantel',
            'diagramm' => ['semester' => null, 'vorsemester' => null, 'zeilen' => $rows, 'grenze' => NotenSkala::genuegend()],
            'tabelle' => $this->hantelTabelle($rows, __('Name')),
            'zusammenfassung' => __(':besser besser, :schlechter schlechter als im Vorsemester.', ['besser' => $besser, 'schlechter' => $schlechter]),
            'meta' => ['titel' => __('Wer hat sich bewegt'), 'optionen' => ['status' => $this->optionen()['status'], 'sort' => ['delta' => $this->optionen()['sort']['delta'], 'name' => $this->optionen()['sort']['name']]]],
        ];
    }

    /** @param  list<array<string, mixed>>  $zeilen */
    private function hantelTabelle(array $zeilen, string $erste): array
    {
        $delta = fn (?float $d) => $d === null ? '–' : ($d > 0 ? '+' : '').NotenSkala::format($d, 2);

        return ['spalten' => [$erste, __('Vorsemester'), __('Aktuell'), __('Veränderung')], 'zeilen' => array_map(
            fn (array $z) => [$z['fach'], NotenSkala::format($z['vorher'], 1), NotenSkala::format($z['jetzt'], 1), $delta($z['delta'])],
            $zeilen,
        )];
    }

    // ── S5 / S6 Benötigte Note ──────────────────────────────────────────────────────────────────────────

    /**
     * Qualifikation: je offene Position «bestanden» oder «benötigt X.X» für genügend (Grenze aus der Konfiguration).
     *
     * @param  list<Leistung>  $leistungen
     */
    public function positionen(array $leistungen, Konfiguration $k): array
    {
        $zeilen = $this->benoetigtZeilen($leistungen, $k, fn (array $r) => $r['knotenId'] !== null);
        $bestanden = count(array_filter($zeilen, fn (array $z) => $z['status'] === Zielrechner::ERREICHT));

        return [
            'schluessel' => 'positionen',
            'diagramm' => ['zeilen' => $zeilen, 'grenze' => $k->genuegend],
            'tabelle' => $this->benoetigtTabelle($zeilen, __('Position')),
            'zusammenfassung' => $zeilen === []
                ? __('Keine offenen Positionen.')
                : __(':n offene Positionen, :b davon bestanden.', ['n' => count($zeilen), 'b' => $bestanden]),
            'meta' => ['titel' => __('Was fehlt zum Bestehen')],
        ];
    }

    /**
     * Kommende Prüfungen mit der Note, die in jeder davon für «genügend» nötig ist.
     *
     * @param  list<Leistung>  $leistungen  inklusive geplanter Prüfungen
     */
    public function pruefungen(array $leistungen, Konfiguration $k, string $zeitraum): array
    {
        $heute = Carbon::today();
        $bis = match ($zeitraum) {
            '4w' => $heute->copy()->addWeeks(4),
            'alle' => null,
            default => $heute->copy()->addWeeks(8),
        };
        $zeilen = $this->benoetigtZeilen($leistungen, $k, fn (array $r) => $r['knotenId'] === null && $r['datum'] !== null
            && $r['datum'] >= $heute->toDateString() && ($bis === null || $r['datum'] <= $bis->toDateString()));

        $satz = __('Keine Prüfungen im gewählten Zeitraum.');
        if ($zeilen !== []) {
            $erste = $zeilen[0];
            $datum = Carbon::parse($erste['datum'])->format('d.m.Y');
            $satz = $erste['status'] === Zielrechner::BENOETIGT
                ? __('Nächste Prüfung am :datum, nötig sind :note.', ['datum' => $datum, 'note' => NotenSkala::format($erste['note'], 2)])
                : __('Nächste Prüfung am :datum.', ['datum' => $datum]);
        }

        return [
            'schluessel' => 'zeitleiste',
            'diagramm' => [
                'von' => $heute->toDateString(),
                'bis' => $bis?->toDateString() ?? ($zeilen === [] ? $heute->toDateString() : end($zeilen)['datum']),
                'zeilen' => $zeilen,
                'grenze' => $k->genuegend,
            ],
            'tabelle' => $this->benoetigtTabelle($zeilen, __('Prüfung'), true),
            'zusammenfassung' => $satz,
            'meta' => ['titel' => __('Nächste Prüfungen'), 'optionen' => ['zeitraum' => $this->optionen()['pruefungen']]],
        ];
    }

    /**
     * @param  list<Leistung>  $leistungen
     * @param  \Closure(array<string, mixed>): bool  $behalten
     * @return list<array{titel: string, datum: ?string, status: string, note: ?float, aktuell: ?float, minimum: ?float, maximum: ?float, grenze: float, knotenId: ?int, text: string}>
     */
    private function benoetigtZeilen(array $leistungen, Konfiguration $k, \Closure $behalten): array
    {
        $namen = $this->knotenNamen($k);
        $out = [];
        foreach ($this->statistik->benoetigt($leistungen, $k) as $r) {
            if (! $behalten($r)) {
                continue;
            }
            $ziel = Zielgroesse::parse($r['ziel']);
            $bezeichnung = match (true) {
                $r['titel'] !== null && $r['titel'] !== '' => $r['titel'],
                $r['knotenId'] !== null => $namen[$r['knotenId']] ?? '–',
                $ziel->ebene === 'fach' => $k->fachName((int) $ziel->id),
                $ziel->ebene === 'modul' => $k->modulName((int) $ziel->id),
                default => __('Gesamtschnitt'),
            };
            $zeile = [
                'titel' => $bezeichnung,
                'datum' => $r['datum'],
                'status' => $r['status'],
                'note' => $r['note'],
                'aktuell' => $r['aktuell'],
                'minimum' => $r['minimum'],
                'maximum' => $r['maximum'],
                'grenze' => $r['grenze'],
                'knotenId' => $r['knotenId'],
            ];
            $zeile['text'] = $this->benoetigtText($zeile);
            $out[] = $zeile;
        }

        return $out;
    }

    /** Text zur nötigen Note: «bestanden», «benötigt 4.5», «nicht erreichbar». */
    public function benoetigtText(array $zeile): string
    {
        return match ($zeile['status']) {
            Zielrechner::ERREICHT, Zielrechner::KEINE_UNBEKANNTEN => __('bestanden'),
            Zielrechner::BENOETIGT => __('benötigt :wert', ['wert' => NotenSkala::format($zeile['note'], 2)]),
            Zielrechner::UNERREICHBAR => __('nicht erreichbar'),
            default => __('ohne Einfluss'),
        };
    }

    /** @param  list<array<string, mixed>>  $zeilen */
    private function benoetigtTabelle(array $zeilen, string $erste, bool $mitDatum = false): array
    {
        return [
            'spalten' => $mitDatum ? [__('Datum'), $erste, __('Nötig')] : [$erste, __('Nötig')],
            'zeilen' => array_map(fn (array $z) => $mitDatum
                ? [$z['datum'] !== null ? Carbon::parse($z['datum'])->format('d.m.Y') : '–', $z['titel'], $this->benoetigtText($z)]
                : [$z['titel'], $this->benoetigtText($z)], $zeilen),
        ];
    }

    // ── S8 Punktstreifen ────────────────────────────────────────────────────────────────────────────────

    /**
     * Ein Punkt je Person mit Note; Median erst ab drei Personen (Verteilung). Namen für Titel und Tabelle.
     *
     * @param  list<array{id: int, name: string, gesamt: float, status: string}>  $punkte
     */
    public function punktstreifen(array $punkte): array
    {
        $punkte = array_values($punkte);
        $median = Verteilung::median(array_column($punkte, 'gesamt'));
        $n = count($punkte);
        $status = fn (string $s) => match ($s) {
            Lernstand::ROT => __('kritisch'),
            Lernstand::GELB => __('beobachten'),
            Lernstand::ABGESCHLOSSEN => __('abgeschlossen'),
            default => __('im Plan'),
        };

        return [
            'schluessel' => 'punktstreifen',
            'diagramm' => ['punkte' => $punkte, 'median' => $median, 'n' => $n, 'grenze' => NotenSkala::genuegend()],
            'tabelle' => ['spalten' => [__('Name'), __('Gesamtschnitt'), __('Status')], 'zeilen' => array_map(
                fn (array $p) => [$p['name'], NotenSkala::format($p['gesamt'], 1), $status($p['status'])],
                $punkte,
            )],
            'zusammenfassung' => match (true) {
                $n === 0 => __('Keine Personen mit Note.'),
                $median === null => __(':n Personen.', ['n' => $n]),
                default => __(':n Personen, Median :median.', ['n' => $n, 'median' => NotenSkala::format($median, 1)]),
            },
            'meta' => ['titel' => __('Wer braucht Aufmerksamkeit')],
        ];
    }

    // ── S9 Erfassung je Woche ───────────────────────────────────────────────────────────────────────────

    /** @param  array{labels: list<string>, werte: list<int>, median: ?float, wochen: int}  $aktivitaet  Uebersicht::admin()['aktivitaet'] */
    public function erfassung(array $aktivitaet): array
    {
        $summe = array_sum($aktivitaet['werte']);

        return [
            'schluessel' => 'erfassung',
            'diagramm' => $aktivitaet,
            'tabelle' => ['spalten' => [__('Woche'), __('Anzahl')], 'zeilen' => array_map(
                fn (string $l, int $w) => [$l, (string) $w],
                $aktivitaet['labels'],
                $aktivitaet['werte'],
            )],
            'zusammenfassung' => $aktivitaet['median'] === null
                ? __(':n Noten in :wochen Wochen.', ['n' => $summe, 'wochen' => $aktivitaet['wochen']])
                : __(':n Noten in :wochen Wochen, Median :median je Woche.', ['n' => $summe, 'wochen' => $aktivitaet['wochen'], 'median' => NotenSkala::format($aktivitaet['median'])]),
            'meta' => ['titel' => __('Erfassung je Woche'), 'optionen' => ['zeitraum' => $this->optionen()['erfassung']]],
        ];
    }

    // ── S10 / S11 Bericht ───────────────────────────────────────────────────────────────────────────────

    /**
     * Notenverteilung in Viertelnoten (S10).
     *
     * @param  array<string, mixed>  $verteilung  Bericht::noten()['verteilung']
     */
    public function verteilung(array $verteilung): array
    {
        $n = (int) $verteilung['n'];

        return [
            'schluessel' => 'histogramm',
            'diagramm' => $verteilung,
            'tabelle' => ['spalten' => [__('Note ab'), __('Anzahl')], 'zeilen' => array_map(
                fn (string $l, int $w) => [$l, (string) $w],
                $verteilung['labels'],
                $verteilung['werte'],
            )],
            'zusammenfassung' => match (true) {
                $n === 0 => __('Keine Zeugnisnoten im Zeitraum.'),
                $verteilung['median'] === null => __(':n Zeugnisnoten.', ['n' => $n]),
                default => __(':n Zeugnisnoten, Median :median.', ['n' => $n, 'median' => NotenSkala::format($verteilung['median'], 2)]),
            },
            'meta' => ['titel' => __('Notenverteilung')],
        ];
    }

    /**
     * Streifen je Lehrjahr (S11).
     *
     * @param  list<array{jahr: int, schnitt: ?float, anzahl: int, werte: list<float>, median: ?float}>  $lehrjahre  Bericht::noten()['nachLehrjahr']
     */
    public function lehrjahre(array $lehrjahre): array
    {
        $mitNote = array_values(array_filter($lehrjahre, fn (array $j) => $j['anzahl'] > 0));

        return [
            'schluessel' => 'lehrjahre',
            'diagramm' => ['jahre' => $lehrjahre, 'grenze' => NotenSkala::genuegend()],
            'tabelle' => ['spalten' => [__('Lehrjahr'), __('Gesamtschnitt'), __('Personen'), __('Median')], 'zeilen' => array_map(
                fn (array $j) => [(string) $j['jahr'], NotenSkala::format($j['schnitt'], 1), (string) $j['anzahl'], NotenSkala::format($j['median'], 1)],
                $lehrjahre,
            )],
            'zusammenfassung' => $mitNote === []
                ? __('Keine Personen mit Note.')
                : __(':n Personen in :jahre Lehrjahren.', ['n' => array_sum(array_column($mitNote, 'anzahl')), 'jahre' => count($mitNote)]),
            'meta' => ['titel' => __('Lehrjahre im Vergleich')],
        ];
    }
}
