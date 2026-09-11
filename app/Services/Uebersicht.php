<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lernender;
use App\Models\Pruefung;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Lernstand;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechner;
use App\Services\Auswertung\Zielrechner;
use App\Support\Einstellungen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daten der drei Dashboards. Jede Zahl stammt aus dem Rechenkern (docs/notenlogik.md).
 */
final class Uebersicht
{
    public function __construct(
        private readonly NotenQuelle $quelle,
        private readonly LernstandRechner $lernstaende,
        private readonly Rechner $rechner,
        private readonly Zielrechner $zielrechner = new Zielrechner,
    ) {}

    /** Lernender: wo stehe ich, wohin geht es, was muss ich tun. */
    public function lernender(Lernender $l, User $user): array
    {
        $id = (int) $l->lernender_id;
        $k = Konfiguration::ausDb();
        $stand = $this->lernstaende->fuer([$id])[$id];
        $a = $stand->auswertung;
        $semesterIds = $a->semesterIds();

        $kategorien = [];
        foreach ($a->kategorien as $kid => $kat) {
            $kategorien[] = [
                'id' => $kid,
                'name' => $k->kategorieName($kid),
                'note' => $kat['note'],
                'verlauf' => array_column($a->verlauf($kid), 'note'),
                'semester' => $stand->semesterId ? $a->semester($stand->semesterId, $kid)['note'] : null,
                'promotion' => $stand->semesterId ? $a->promotion($kid, $stand->semesterId) : null,
            ];
        }

        $pruefungen = $l->pruefungen()->offen()->with(['fach', 'modul'])->orderBy('datum')->get();
        $heute = now()->startOfDay();
        $ueberfaellig = $pruefungen->filter(fn (Pruefung $p) => $p->datum->lt($heute))->values();
        $naechste = $pruefungen->filter(fn (Pruefung $p) => $p->datum->gte($heute))->take(5)->values();

        $ungeleseneKommentare = DB::table('noten as n')
            ->join('noten_kommentare as nk', 'nk.note_id', '=', 'n.note_id')
            ->leftJoin('noten_gesehen as ng', fn ($j) => $j->on('ng.note_id', '=', 'n.note_id')->where('ng.viewer_benutzer_id', '=', $user->benutzer_id))
            ->where('n.lernender_id', $id)->whereNull('n.geloescht_am')
            ->where('nk.autor_benutzer_id', '!=', $user->benutzer_id)
            ->where(fn ($q) => $q->whereNull('ng.gesehen_am')->orWhereColumn('nk.erstellt_am', '>', 'ng.gesehen_am'))
            ->distinct()->count('n.note_id');

        $katalog = $this->rechner->katalog($l);
        $lehrsemester = collect($katalog['semester'])->search(fn ($s) => $s['id'] === $katalog['aktuelles_semester']);
        $geplanteModule = $pruefungen->pluck('modul_id')->filter()->all();
        $fehlendeModule = $lehrsemester === false ? [] : collect($katalog['module'])
            ->filter(fn ($m) => $m['empfohlen'] !== null && $m['empfohlen'] <= $lehrsemester && ! isset($a->elemente['m'.$m['id']]) && ! in_array($m['id'], $geplanteModule, true))
            ->values()->all();

        return [
            'stand' => $stand,
            'auswertung' => $a,
            'kategorien' => $kategorien,
            'ziele' => $this->zieleMitBedarf($l, $a),
            'zuTun' => $this->zuTunLernender($ueberfaellig, $ungeleseneKommentare, $fehlendeModule, $stand, $a),
            'naechste' => $naechste,
            'verlauf' => $this->verlaufDiagramm($a, $semesterIds),
            'balken' => $this->balkenDiagramm($a, $stand->semesterId),
            'letzteNoten' => $l->noten()->with(['fach', 'modulBelegung.modul'])->latest('pruefungsdatum')->latest('note_id')->limit(4)->get(),
            'lehrzeit' => $this->lehrzeit($l),
            'grenzen' => \App\Support\NotenSkala::grenzen(),
        ];
    }

    /** Berufsbildner: wo steht wer, und wo kippt es. */
    public function berufsbildner(User $user): array
    {
        $lernende = Lernender::sichtbarFuer($user)
            ->whereHas('benutzer', fn ($q) => $q->where('aktiv', true))
            ->with(['benutzer', 'lehrberuf'])
            ->get();
        $ids = $lernende->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();
        $staende = $this->lernstaende->fuer($ids);
        $neu = $this->ungeseheneNoten($ids, (int) $user->benutzer_id);

        $zeilen = $lernende->map(fn (Lernender $l) => (object) [
            'lernender' => $l,
            'stand' => $staende[$l->lernender_id],
            'neu' => (int) ($neu[$l->lernender_id] ?? 0),
            'lehrjahr' => $l->lehrjahr(),
        ])->sortBy([fn ($a, $b) => $a->stand->rang() <=> $b->stand->rang(), fn ($a, $b) => strcoll($a->lernender->benutzer->nachname, $b->lernender->benutzer->nachname)])->values();

        return [
            'zeilen' => $zeilen,
            'kennzahlen' => [
                'lernende' => $zeilen->count(),
                'neu' => $zeilen->sum('neu'),
                'rot' => $zeilen->filter(fn ($z) => $z->stand->status === Lernstand::ROT)->count(),
                'gelb' => $zeilen->filter(fn ($z) => $z->stand->status === Lernstand::GELB)->count(),
            ],
            'brennpunkte' => $this->brennpunkte($zeilen),
            'vergleich' => $this->vergleichDiagramm($zeilen),
            'pruefungen' => Pruefung::query()->offen()->whereIn('lernender_id', $ids)->with(['fach', 'modul', 'lernender.benutzer'])
                ->whereBetween('datum', [now()->toDateString(), now()->addDays(14)->toDateString()])->orderBy('datum')->get(),
            'lehrende' => $this->lehrendeBald($lernende),
            'grenzen' => \App\Support\NotenSkala::grenzen(),
        ];
    }

    /** Admin: Betrieb überblicken, Lücken in der Einrichtung sehen. */
    public function admin(): array
    {
        $lernende = Lernender::query()->whereHas('benutzer', fn ($q) => $q->where('aktiv', true))->with(['benutzer', 'lehrberuf'])->get();
        $ids = $lernende->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();
        $staende = $this->lernstaende->fuer($ids);
        $k = Konfiguration::ausDb();
        $aktuell = $k->semesterFuerDatum(now()->toDateString());

        $bbs = DB::table('berufsbildner as bb')->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')->whereNull('b.geloescht_am')->where('b.aktiv', 1)
            ->orderBy('b.nachname')->get(['bb.berufsbildner_id', 'b.benutzer_id', 'b.vorname', 'b.nachname']);
        $betreuung = DB::table('betreuungen')->whereIn('lernender_id', $ids)->where('gueltig_von', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', now()->toDateString()))
            ->get(['berufsbildner_id', 'lernender_id']);

        $proBb = $bbs->map(function ($bb) use ($betreuung, $staende) {
            $betreut = $betreuung->where('berufsbildner_id', $bb->berufsbildner_id)->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();
            $neu = array_sum($this->ungeseheneNoten($betreut, (int) $bb->benutzer_id));

            return (object) [
                'name' => $bb->vorname.' '.$bb->nachname,
                'lernende' => count($betreut),
                'rot' => count(array_filter($betreut, fn ($id) => ($staende[$id] ?? null)?->status === Lernstand::ROT)),
                'gelb' => count(array_filter($betreut, fn ($id) => ($staende[$id] ?? null)?->status === Lernstand::GELB)),
                'neu' => $neu,
            ];
        });

        return [
            'kennzahlen' => [
                'lernende' => count($ids),
                'berufsbildner' => $bbs->count(),
                'noten_semester' => $aktuell ? DB::table('noten')->whereNull('geloescht_am')->where('semester_id', $aktuell)->count() : 0,
                'semester' => $k->semesterName($aktuell),
                'feedback' => DB::table('feedback')->where('status', 'offen')->count(),
                'rot' => count(array_filter($staende, fn (Lernstand $s) => $s->status === Lernstand::ROT)),
                'gelb' => count(array_filter($staende, fn (Lernstand $s) => $s->status === Lernstand::GELB)),
            ],
            'einrichtung' => $this->einrichtungsluecken(
                $lernende->filter(fn (Lernender $l) => ! $l->lehrende || ! $l->lehrende->isPast())->pluck('lernender_id')->map(fn ($v) => (int) $v)->all(),
                $betreuung
            ),
            'proBb' => $proBb,
            'aktivitaet' => $this->aktivitaet(),
            'jahrgaenge' => $this->jahrgangsDiagramm($lernende, $staende),
            'lehrende' => $this->lehrendeBald($lernende),
            'kritisch' => $lernende->filter(fn (Lernender $l) => $staende[$l->lernender_id]->status === Lernstand::ROT)
                ->map(fn (Lernender $l) => (object) ['lernender' => $l, 'stand' => $staende[$l->lernender_id]])->values(),
            'grenzen' => \App\Support\NotenSkala::grenzen(),
        ];
    }

    /** Lernenden-Detail für Admin und Berufsbildner. */
    public function lernendenDetail(Lernender $l, string $bereich): array
    {
        $id = (int) $l->lernender_id;
        $stand = $this->lernstaende->fuer([$id])[$id];
        $a = $stand->auswertung;
        $pruefungen = $l->pruefungen()->offen()->with(['fach', 'modul'])->orderBy('datum')->get();

        return [
            'stand' => $stand,
            'heatmap' => $this->heatmap($a),
            'verlauf' => $this->verlaufDiagramm($a, $a->semesterIds()),
            'ziele' => $this->zieleMitBedarf($l, $a, fn (string $ziel, float $wert) => route($bereich.'.lernende.rechner', ['lernender_id' => $id, 'ziel' => $ziel, 'zielwert' => $wert])),
            'pruefungen' => $pruefungen,
        ];
    }

    /**
     * Zeugnisnoten als Matrix: Zeilen = Fächer und Module je Kategorie, Spalten = Semester.
     *
     * @return array{semester: list<array{id: int, name: string}>, gruppen: list<array<string, mixed>>, semesterschnitt: array<int, ?float>, gesamt: ?float}
     */
    public function heatmap(Auswertung $a): array
    {
        $k = $a->konfiguration;
        $semesterIds = $a->semesterIds();
        $gruppen = [];

        foreach ($a->kategorien as $kid => $kat) {
            $zeilen = [];
            foreach ($a->elementeDerKategorie($kid) as $e) {
                $schluessel = $e->typ === Element::FACH ? 'f'.$e->fachId : 'm'.$e->modulId;
                $zeilen[$schluessel] ??= [
                    'label' => $e->label,
                    'typ' => $e->typ,
                    'zellen' => [],
                    'lehrzeit' => $e->typ === Element::FACH ? $a->fach($e->fachId)['note'] : $e->note,
                    'offen' => $e->typ === Element::MODUL && ! $e->abgeschlossen() && $e->offenGewicht() !== null,
                ];
                if ($e->semesterId !== null) {
                    $zeilen[$schluessel]['zellen'][$e->semesterId] = $e->note;
                }
            }
            $gruppen[] = ['name' => $k->kategorieName($kid), 'note' => $kat['note'], 'zeilen' => array_values($zeilen),
                'semester' => array_combine($semesterIds, array_map(fn ($s) => $a->semester($s, $kid)['note'], $semesterIds)) ?: []];
        }

        return [
            'semester' => array_map(fn ($s) => ['id' => $s, 'name' => $k->semesterName($s)], $semesterIds),
            'gruppen' => $gruppen,
            'semesterschnitt' => array_combine($semesterIds, array_map(fn ($s) => $a->semester($s)['note'], $semesterIds)) ?: [],
            'gesamt' => $a->gesamtNote,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function zieleMitBedarf(Lernender $l, Auswertung $a, ?\Closure $link = null): array
    {
        $link ??= fn (string $ziel, float $wert) => route('lernender.noten.rechner', ['ziel' => $ziel, 'zielwert' => $wert]);
        $ziele = Ziel::query()->where('lernender_id', $l->lernender_id)->get();
        if ($ziele->isEmpty()) {
            return [];
        }

        $k = $a->konfiguration;
        $leistungen = [...$this->quelle->fuerLernenden((int) $l->lernender_id), ...$this->rechner->offeneLeistungen($l, $a)];

        return $ziele->map(function (Ziel $z) use ($a, $k, $leistungen, $link) {
            $g = $z->zielgroesse();
            $loesung = $this->zielrechner->loese($leistungen, $g, (float) $z->zielwert, $k);

            return [
                'label' => $g->label($k),
                'zielwert' => (float) $z->zielwert,
                'aktuell' => $a->wert($g),
                'loesung' => $loesung,
                'link' => $link((string) $g, (float) $z->zielwert),
            ];
        })->all();
    }

    /** @return list<array{text: string, detail: ?string, link: string, ton: string}> */
    private function zuTunLernender(Collection $ueberfaellig, int $kommentare, array $fehlendeModule, Lernstand $stand, Auswertung $a): array
    {
        $liste = [];
        foreach ($ueberfaellig as $p) {
            $liste[] = ['text' => 'Note eintragen: '.$p->bezeichnung(), 'detail' => 'Prüfung vom '.$p->datum->format('d.m.'),
                'link' => route('lernender.noten.create', ['pruefung' => $p->pruefung_id]), 'ton' => 'gelb'];
        }
        foreach ($stand->promotion as $p) {
            $kid = $p['kategorie_id'];
            $liste[] =['text' => 'Promotion '.$p['kategorie'].' gefährdet', 'detail' => $a->konfiguration->semesterName($stand->semesterId),
                'link' => route('lernender.noten.rechner', ['ziel' => 'kategorie:'.$kid.'@semester:'.$stand->semesterId, 'zielwert' => $a->konfiguration->kategorien[$kid]['promotion_min_schnitt'] ?? $a->konfiguration->genuegend]),
                'ton' => 'rot'];
        }
        if ($kommentare > 0) {
            $liste[] = ['text' => $kommentare === 1 ? '1 Note mit neuem Kommentar' : $kommentare.' Noten mit neuen Kommentaren', 'detail' => null,
                'link' => route('lernender.noten.index'), 'ton' => 'accent'];
        }
        foreach (array_slice($fehlendeModule, 0, 4) as $m) {
            $liste[] = ['text' => $m['name'], 'detail' => 'noch keine Note', 'link' => route('lernender.noten.create', ['bezug' => 'modul:'.$m['id']]), 'ton' => 'neutral'];
        }

        return $liste;
    }

    private function verlaufDiagramm(Auswertung $a, array $semesterIds): array
    {
        $k = $a->konfiguration;
        $serien = [['name' => 'Semesterschnitt', 'werte' => array_map(fn ($s) => $a->semester($s)['note'], $semesterIds), 'farbe' => '--accent', 'dick' => true]];
        foreach (array_keys($a->kategorien) as $kid) {
            $serien[] = ['name' => $k->kategorieName($kid), 'werte' => array_map(fn ($s) => $a->semester($s, $kid)['note'], $semesterIds)];
        }

        $faecher = [];
        foreach ($a->elemente as $e) {
            if ($e->typ === Element::FACH && ! isset($faecher[$e->fachId])) {
                $faecher[$e->fachId] = ['name' => $e->label, 'werte' => array_map(fn ($s) => $a->elemente["f{$e->fachId}s{$s}"]->note ?? null, $semesterIds)];
            }
        }
        uasort($faecher, fn ($x, $y) => strcoll($x['name'], $y['name']));

        return ['labels' => array_map(fn ($s) => $k->semesterName($s), $semesterIds), 'serien' => $serien, 'faecher' => array_values($faecher), 'grenze' => $k->genuegend];
    }

    /** Stärken und Schwächen: Zeugnisnoten im Bezugssemester und über die Lehrzeit. */
    private function balkenDiagramm(Auswertung $a, ?int $semesterId): array
    {
        $semester = $semesterId ? $a->semester($semesterId)['elemente'] : [];
        usort($semester, fn (Element $x, Element $y) => $x->note <=> $y->note);

        $lehrzeit = [];
        foreach ($a->elemente as $e) {
            if ($e->typ === Element::MODUL && $e->note !== null) {
                $lehrzeit[$e->label] = $e->note;
            } elseif ($e->typ === Element::FACH && ! isset($lehrzeit[$e->label])) {
                $lehrzeit[$e->label] = $a->fach($e->fachId)['note'];
            }
        }
        asort($lehrzeit);

        return [
            'semester' => ['name' => $a->konfiguration->semesterName($semesterId), 'labels' => array_map(fn (Element $e) => $e->label, $semester), 'werte' => array_map(fn (Element $e) => $e->note, $semester)],
            'lehrzeit' => ['labels' => array_keys($lehrzeit), 'werte' => array_values($lehrzeit)],
        ];
    }

    private function lehrzeit(Lernender $l): ?array
    {
        if (! $l->lehrbeginn || ! $l->lehrende) {
            return null;
        }
        $gesamt = max(1, $l->lehrbeginn->diffInDays($l->lehrende));
        $vergangen = $l->lehrbeginn->diffInDays(now(), false);

        return [
            'beginn' => $l->lehrbeginn,
            'ende' => $l->lehrende,
            'prozent' => (int) max(0, min(100, round($vergangen / $gesamt * 100))),
            'lehrjahr' => $l->lehrjahr(),
            'tage' => (int) now()->startOfDay()->diffInDays($l->lehrende, false),
            'beruf' => $l->lehrberuf?->name,
        ];
    }

    /** @param  list<int>  $ids  @return array<int, int> ungesehene Noten je Lernender für einen Betrachter */
    private function ungeseheneNoten(array $ids, int $viewerId): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('noten as n')
            ->leftJoin('noten_gesehen as ng', fn ($j) => $j->on('ng.note_id', '=', 'n.note_id')->where('ng.viewer_benutzer_id', '=', $viewerId))
            ->whereIn('n.lernender_id', $ids)->whereNull('n.geloescht_am')
            ->where(fn ($q) => $q->whereNull('ng.gesehen_am')
                ->orWhereColumn('n.aktualisiert_am', '>', 'ng.gesehen_am')
                ->orWhereExists(fn ($k) => $k->select(DB::raw(1))->from('noten_kommentare as k')
                    ->whereColumn('k.note_id', 'n.note_id')->whereColumn('k.erstellt_am', '>', 'ng.gesehen_am')
                    ->where('k.autor_benutzer_id', '!=', $viewerId)))
            ->groupBy('n.lernender_id')
            ->selectRaw('n.lernender_id, COUNT(DISTINCT n.note_id) as anzahl')
            ->pluck('anzahl', 'lernender_id')
            ->map(fn ($v) => (int) $v)->all();
    }

    /** Einzelne Zeugnisnoten, die kippen: ungenügend oder deutlicher Rückgang. */
    private function brennpunkte(Collection $zeilen): array
    {
        $liste = [];
        foreach ($zeilen as $z) {
            foreach ($z->stand->ungenuegend as $e) {
                $liste[] = ['zeile' => $z, 'label' => $e->label, 'note' => $e->note, 'vorher' => null];
            }
            foreach ($z->stand->einbrueche as $e) {
                $liste[] = ['zeile' => $z, 'label' => $e['label'], 'note' => $e['nachher'], 'vorher' => $e['vorher']];
            }
        }
        usort($liste, fn ($a, $b) => $a['note'] <=> $b['note']);

        return array_slice($liste, 0, 8);
    }

    private function vergleichDiagramm(Collection $zeilen): array
    {
        $sortiert = $zeilen->filter(fn ($z) => $z->stand->auswertung->gesamtNote !== null)
            ->sortByDesc(fn ($z) => $z->stand->auswertung->gesamtNote)->values();

        return [
            'labels' => $sortiert->map(fn ($z) => $z->lernender->benutzer->vorname.' '.mb_substr($z->lernender->benutzer->nachname, 0, 1).'.')->all(),
            'gesamt' => $sortiert->map(fn ($z) => $z->stand->auswertung->gesamtNote)->all(),
            'semester' => $sortiert->map(fn ($z) => $z->stand->semesterNote)->all(),
        ];
    }

    private function lehrendeBald(Collection $lernende): Collection
    {
        $frist = (int) Einstellungen::get(Einstellungen::FRIST_LEHRENDE_TAGE, '60');

        return $lernende->filter(fn (Lernender $l) => $l->lehrende && $l->lehrende->isFuture() && $l->lehrende->lte(now()->addDays($frist)))
            ->sortBy('lehrende')->values();
    }

    /** @return list<array{text: string, anzahl: int, link: string}> */
    private function einrichtungsluecken(array $ids, Collection $betreuung): array
    {
        $heute = now()->toDateString();
        $ohneBetreuung = count(array_diff($ids, $betreuung->pluck('lernender_id')->map(fn ($v) => (int) $v)->unique()->all()));
        $ohneTrack = $ids === [] ? 0 : count(array_diff($ids, DB::table('lernender_tracks')->whereIn('lernender_id', $ids)->where('start_datum', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('end_datum')->orWhere('end_datum', '>=', $heute))->distinct()->pluck('lernender_id')->map(fn ($v) => (int) $v)->all()));
        $letztesSemesterEnde = DB::table('semester')->max('end_datum');
        $semesterReichtBis = $letztesSemesterEnde ? Carbon::parse($letztesSemesterEnde) : null;

        $luecken = [
            ['text' => 'Lernende ohne aktive Betreuung', 'anzahl' => $ohneBetreuung, 'link' => route('admin.lernende.index')],
            ['text' => 'Lernende ohne aktiven Track', 'anzahl' => $ohneTrack, 'link' => route('admin.lernende.index')],
            ['text' => 'Module ohne Lernort', 'anzahl' => DB::table('lehrberuf_module')->where('aktiv', 1)->whereNull('kategorie_id')->count(), 'link' => route('admin.stammdaten.lehrberufe.index')],
            ['text' => 'Fächer ohne Kategorie', 'anzahl' => DB::table('faecher')->where('aktiv', 1)->whereNull('kategorie_id')->count(), 'link' => route('admin.stammdaten.faecher.index')],
            ['text' => 'Lehrberufe ohne Module', 'anzahl' => DB::table('lehrberufe as lb')->where('lb.aktiv', 1)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('lehrberuf_module as m')->whereColumn('m.lehrberuf_id', 'lb.lehrberuf_id'))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('lehrberuf_faecher as f')->whereColumn('f.lehrberuf_id', 'lb.lehrberuf_id'))->count(),
                'link' => route('admin.stammdaten.lehrberufe.index')],
            ['text' => $semesterReichtBis ? 'Semester erfasst bis '.$semesterReichtBis->format('d.m.Y') : 'Keine Semester erfasst',
                'anzahl' => ! $semesterReichtBis || $semesterReichtBis->lt(now()->addMonths(6)) ? 1 : 0, 'link' => route('admin.stammdaten.semester.index')],
        ];

        return array_values(array_filter($luecken, fn ($l) => $l['anzahl'] > 0));
    }

    /** Neu erfasste Noten pro Woche (12 Wochen). */
    private function aktivitaet(): array
    {
        $start = now()->startOfWeek()->subWeeks(11);
        $roh = DB::table('noten')->whereNull('geloescht_am')->where('erstellt_am', '>=', $start)
            ->selectRaw('YEARWEEK(erstellt_am, 3) as kw, COUNT(*) as anzahl')->groupBy('kw')->pluck('anzahl', 'kw');

        $labels = [];
        $werte = [];
        for ($i = 0; $i < 12; $i++) {
            $w = $start->copy()->addWeeks($i);
            $labels[] = 'KW '.$w->isoWeek();
            $werte[] = (int) ($roh[(int) $w->format('oW')] ?? 0);
        }

        return ['labels' => $labels, 'werte' => $werte];
    }

    /** Gesamtschnitt je Lehrberuf und Lehrjahr. */
    private function jahrgangsDiagramm(Collection $lernende, array $staende): array
    {
        $gruppen = [];
        foreach ($lernende as $l) {
            $note = $staende[$l->lernender_id]->auswertung->gesamtNote;
            $jahr = $l->lehrjahr();
            if ($note !== null && $jahr !== null && $jahr <= 4) {
                $gruppen[$l->lehrberuf?->kuerzel ?? '–'][$jahr][] = $note;
            }
        }
        ksort($gruppen);

        return [
            'labels' => ['1. Lehrjahr', '2. Lehrjahr', '3. Lehrjahr', '4. Lehrjahr'],
            'serien' => array_map(fn ($beruf, $jahre) => ['name' => $beruf, 'werte' => array_map(
                fn ($j) => isset($jahre[$j]) ? round(array_sum($jahre[$j]) / count($jahre[$j]), 2) : null, [1, 2, 3, 4])], array_keys($gruppen), $gruppen),
        ];
    }
}
