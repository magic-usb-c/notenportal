<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarFeed;
use App\Models\Feedback;
use App\Models\Lernender;
use App\Models\MailLog;
use App\Models\Pruefung;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\Element;
use App\Services\Auswertung\Konfiguration;
use App\Services\Auswertung\Lernstand;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Auswertung\Modulstatus;
use App\Services\Auswertung\NotenQuelle;
use App\Services\Auswertung\Rechner;
use App\Services\Auswertung\Rundung;
use App\Services\Auswertung\Zielrechner;
use App\Services\Betrieb\Sicherung;
use App\Support\Betrieb;
use App\Support\Format;
use App\Support\NotenSkala;
use App\Support\Ungelesen;
use App\Support\Zahl;
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
        private readonly Modulstatus $modulstatus = new Modulstatus,
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

        $notenMitNeuenKommentaren = DB::table('noten as n')
            ->join('noten_kommentare as nk', 'nk.note_id', '=', 'n.note_id')
            ->leftJoin('noten_gesehen as ng', fn ($j) => $j->on('ng.note_id', '=', 'n.note_id')->where('ng.viewer_benutzer_id', '=', $user->benutzer_id))
            ->where('n.lernender_id', $id)->whereNull('n.geloescht_am')
            ->where('nk.autor_benutzer_id', '!=', $user->benutzer_id)
            ->where(fn ($q) => $q->whereNull('ng.gesehen_am')->orWhereColumn('nk.erstellt_am', '>', 'ng.gesehen_am'))
            ->distinct()->pluck('n.note_id');
        $ungeleseneKommentare = $notenMitNeuenKommentaren->count();

        $katalog = $this->rechner->katalog($l);
        $lehrsemester = collect($katalog['semester'])->search(fn ($s) => $s['id'] === $katalog['aktuelles_semester']);
        $geplanteModule = $pruefungen->pluck('modul_id')->filter()->all();
        $fehlendeModule = $lehrsemester === false ? [] : collect($katalog['module'])
            ->filter(fn ($m) => $m['empfohlen'] !== null && $m['empfohlen'] <= $lehrsemester && ! isset($a->elemente['m'.$m['id']]) && ! in_array($m['id'], $geplanteModule, true))
            ->values()->all();

        $zuTun = $this->zuTunLernender($ueberfaellig, $ungeleseneKommentare, $notenMitNeuenKommentaren->first(), $fehlendeModule, $stand, $a);

        return [
            'stand' => $stand,
            'auswertung' => $a,
            'kategorien' => $kategorien,
            'ziele' => $this->zieleMitBedarf($l, $a),
            'zielGesamt' => $this->lernenderGesamtziel($l),
            'alsNaechstes' => $this->lernenderAlsNaechstes($zuTun, $stand, $naechste, $heute),
            'verlauf' => $this->verlaufDiagramm($a, $semesterIds),
            'balken' => $this->balkenDiagramm($a, $stand->semesterId),
            'letzteNoten' => $l->noten()->with(['fach', 'modulBelegung.modul'])->latest('pruefungsdatum')->latest('note_id')->limit(4)->get(),
            'lehrzeit' => $this->lehrzeit($l),
            'grenzen' => NotenSkala::grenzen(),
        ];
    }

    /**
     * «Als Nächstes»: Überfälliges und Gefährdetes oben, dann ungenügende Zeugnisnoten, Hinweise,
     * geplante Prüfungen und zuletzt Module ohne Note.
     *
     * @param  list<array{text: string, detail: ?string, link: string, ton: string}>  $zuTun
     * @return list<array{text: string, detail: ?string, link: string, ton: string, datum: ?Carbon, rechts: ?string}>
     */
    private function lernenderAlsNaechstes(array $zuTun, Lernstand $stand, Collection $naechste, Carbon $heute): array
    {
        $nach = fn (string ...$toene) => array_values(array_filter($zuTun, fn (array $t) => in_array($t['ton'], $toene, true)));

        $ungenuegend = [];
        if ($stand->ungenuegend) {
            $anzahl = count($stand->ungenuegend);
            $ungenuegend[] = [
                'text' => $anzahl === 1
                    ? __('Ungenügend: :name', ['name' => $stand->ungenuegend[0]->label])
                    : __(':anzahl ungenügende Zeugnisnoten', ['anzahl' => $anzahl]),
                'detail' => $anzahl === 1
                    ? __('Zeugnisnote :note', ['note' => NotenSkala::format($stand->ungenuegend[0]->note)])
                    : implode(', ', array_map(fn (Element $e) => $e->label, $stand->ungenuegend)),
                'link' => route('learner.grades.index', ['semester_id' => $stand->semesterId]),
                'ton' => 'rot',
            ];
        }

        $termine = $naechste->map(function (Pruefung $p) use ($heute) {
            $tage = (int) $heute->diffInDays($p->datum, false);

            return [
                'text' => $p->bezeichnung(),
                'detail' => match (true) {
                    $tage <= 0 => __('heute'),
                    $tage === 1 => __('morgen'),
                    default => __('in :anzahl Tagen', ['anzahl' => $tage]),
                },
                'link' => route('learner.exams.index'),
                'ton' => 'termin',
                'datum' => $p->datum,
                'rechts' => __('Gewicht').' '.Zahl::prozent($p->gewichtung_prozent),
            ];
        })->all();

        return array_map(
            fn (array $t) => $t + ['datum' => null, 'rechts' => null],
            [...$nach('gelb', 'rot'), ...$ungenuegend, ...$nach('accent'), ...$termine, ...$nach('neutral')],
        );
    }

    /** Zielmarke im Bullet Graph: gesetztes Ziel für den Gesamtschnitt. */
    private function lernenderGesamtziel(Lernender $l): ?float
    {
        $ziel = Ziel::query()->where('lernender_id', $l->lernender_id)->get()
            ->first(fn (Ziel $z) => (string) $z->zielgroesse() === 'gesamt');

        return $ziel ? (float) $ziel->zielwert : null;
    }

    /** Sortierschlüssel der Klassentabelle «Meine Lernenden», Reihenfolge = Spaltenreihenfolge in der Tabelle. */
    public const array BB_SORTIERUNGEN = ['name', 'status', 'semester', 'gesamt', 'trend'];

    /** Berufsbildner: wen muss ich heute anschauen? */
    public function berufsbildner(User $user, ?string $sort = null, string $dir = 'asc'): array
    {
        $lernende = Lernender::sichtbarFuer($user)
            ->whereHas('benutzer', fn ($q) => $q->where('aktiv', true))
            ->with(['benutzer', 'lehrberuf'])
            ->get();
        $ids = $lernende->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();
        $staende = $this->lernstaende->fuer($ids);
        $neu = $this->ungeseheneNoten($ids, (int) $user->benutzer_id);
        $pruefungen = Pruefung::query()->offen()->whereIn('lernender_id', $ids)->with(['fach', 'modul', 'lernender.benutzer'])
            ->where('datum', '>=', now()->toDateString())->orderBy('datum')->get();
        $naechstePruefung = $pruefungen->groupBy('lernender_id')->map->first();

        $zeilen = $lernende->map(fn (Lernender $l) => (object) [
            'lernender' => $l,
            'stand' => $staende[$l->lernender_id],
            'neu' => (int) ($neu[$l->lernender_id] ?? 0),
            'lehrjahr' => $l->lehrjahr(),
            'naechstePruefung' => $naechstePruefung->get($l->lernender_id),
        ]);
        $zeilen = $this->bbSortiert($zeilen, $sort, $dir);

        return [
            'zeilen' => $zeilen,
            'aufmerksamkeit' => $this->bbAufmerksamkeit($zeilen),
            'agenda' => $this->bbAgenda($pruefungen->filter(fn (Pruefung $p) => $p->datum->lte(now()->addDays(14)))->values()),
            'lehrende' => $this->lehrendeBald($lernende),
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
        $neuJeBb = $this->ungeseheneNotenProBb($bbs->pluck('berufsbildner_id')->map(fn ($v) => (int) $v)->all(), $ids);

        $proBb = $bbs->map(function ($bb) use ($betreuung, $staende, $neuJeBb) {
            $betreut = $betreuung->where('berufsbildner_id', $bb->berufsbildner_id)->pluck('lernender_id')->map(fn ($v) => (int) $v)->all();
            $neu = $neuJeBb[(int) $bb->berufsbildner_id] ?? 0;

            return (object) [
                'id' => (int) $bb->berufsbildner_id,
                'name' => $bb->vorname.' '.$bb->nachname,
                'lernende' => count($betreut),
                'rot' => count(array_filter($betreut, fn ($id) => ($staende[$id] ?? null)?->status === Lernstand::ROT)),
                'gelb' => count(array_filter($betreut, fn ($id) => ($staende[$id] ?? null)?->status === Lernstand::GELB)),
                'neu' => $neu,
            ];
        });

        $feedbackOffen = Feedback::hauptmeldungen()->where('status', 'offen')->count();
        $einrichtung = $this->einrichtungsluecken(
            $lernende->filter(fn (Lernender $l) => ! $l->lehrende || ! $l->lehrende->lt(today()))->pluck('lernender_id')->map(fn ($v) => (int) $v)->all(),
            $betreuung
        );
        $kritisch = $lernende->filter(fn (Lernender $l) => $staende[$l->lernender_id]->status === Lernstand::ROT)
            ->map(fn (Lernender $l) => (object) ['lernender' => $l, 'stand' => $staende[$l->lernender_id]])->values();

        return [
            'kennzahlen' => [
                'lernende' => count($ids),
                'berufsbildner' => $bbs->count(),
                'noten_semester' => $aktuell ? DB::table('noten')->whereNull('geloescht_am')->where('semester_id', $aktuell)->count() : 0,
                'semester' => $k->semesterName($aktuell),
                'feedback' => $feedbackOffen,
                'rot' => count(array_filter($staende, fn (Lernstand $s) => $s->status === Lernstand::ROT)),
                'gelb' => count(array_filter($staende, fn (Lernstand $s) => $s->status === Lernstand::GELB)),
            ],
            'handlungsbedarf' => $this->adminHandlungsbedarf($einrichtung, $kritisch, $feedbackOffen),
            'proBb' => $proBb,
            'aktivitaet' => $this->aktivitaet(),
            'lehrende' => $this->lehrendeBald($lernende),
            'grenzen' => NotenSkala::grenzen(),
        ];
    }

    /**
     * Admin-Dashboard: eine Liste für allen Handlungsbedarf (Einrichtungslücken, Sicherung
     * älter als 2 Tage, offene Feedback-Meldungen, fehlgeschlagene Jobs/Mails, Kalenderabgleich-
     * Fehler, kritische Lernende), statt vier Karten. Dieselben Prüfpunkte, feiner, liefert
     * `php artisan notenportal:bereitschaft` (App\Support\Bereitschaft).
     *
     * @return list<array{text: string, meta: ?string, badge: ?int, note: ?float, ton: string, symbol: string, link: string}>
     */
    private function adminHandlungsbedarf(array $einrichtung, Collection $kritisch, int $feedbackOffen): array
    {
        $eintraege = [];

        foreach ($einrichtung as $e) {
            $eintraege[] = ['text' => $e['text'], 'meta' => null, 'badge' => $e['anzahl'], 'note' => null, 'ton' => 'gelb', 'symbol' => 'wrench-screwdriver', 'link' => $e['link']];
        }

        $letzteSicherung = app(Sicherung::class)->letzte();
        if (! $letzteSicherung || $letzteSicherung->lt(now()->subDays(2))) {
            $tage = $letzteSicherung ? (int) $letzteSicherung->diffInDays(now()) : null;
            $eintraege[] = [
                'text' => $tage !== null ? __('Letzte Sicherung vor :tage Tagen', ['tage' => $tage]) : __('Noch keine Sicherung erstellt'),
                'meta' => null,
                'badge' => $tage,
                'note' => null,
                'ton' => $tage !== null ? 'gelb' : 'rot',
                'symbol' => 'circle-stack',
                'link' => route('admin.operations.edit'),
            ];
        }

        if ($feedbackOffen > 0) {
            $eintraege[] = [
                'text' => $feedbackOffen === 1 ? __('1 offene Meldung') : __(':anzahl offene Meldungen', ['anzahl' => $feedbackOffen]),
                'meta' => null,
                'badge' => $feedbackOffen,
                'note' => null,
                'ton' => 'accent',
                'symbol' => 'chat-bubble-left-ellipsis',
                'link' => route('admin.feedback.index'),
            ];
        }

        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $eintraege[] = [
                'text' => $failedJobs === 1 ? __('1 fehlgeschlagener Job') : __(':anzahl fehlgeschlagene Jobs', ['anzahl' => $failedJobs]),
                'meta' => null,
                'badge' => $failedJobs,
                'note' => null,
                'ton' => 'rot',
                'symbol' => 'exclamation-triangle',
                'link' => route('admin.mail-log.index'),
            ];
        }

        $fehlgeschlageneMails = MailLog::where('status', MailLog::FAILED)->where('created_at', '>=', now()->subDays(7))->count();
        if ($fehlgeschlageneMails > 0) {
            $eintraege[] = [
                'text' => $fehlgeschlageneMails === 1
                    ? __('1 fehlgeschlagene Mail (7 Tage)')
                    : __(':anzahl fehlgeschlagene Mails (7 Tage)', ['anzahl' => $fehlgeschlageneMails]),
                'meta' => null,
                'badge' => $fehlgeschlageneMails,
                'note' => null,
                'ton' => 'gelb',
                'symbol' => 'envelope',
                'link' => route('admin.mail-log.index'),
            ];
        }

        $kalenderFehler = CalendarFeed::query()->where('last_status', CalendarFeed::ERROR)
            ->whereHas('lernender', fn ($q) => $q->whereNull('geloescht_am'))
            ->with('lernender.benutzer')->get();
        // Ein Eintrag je betroffene Person: es gibt keine Admin-Liste aller Feeds, der Link führt zur Person.
        foreach ($kalenderFehler->groupBy('lernender_id') as $feeds) {
            $anzahl = $feeds->count();
            $lernender = $feeds->first()->lernender;
            $eintraege[] = [
                'text' => $anzahl === 1 ? __('1 Kalenderabgleich mit Fehler') : __(':anzahl Kalenderabgleiche mit Fehlern', ['anzahl' => $anzahl]),
                'meta' => $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname,
                'badge' => $anzahl,
                'note' => null,
                'ton' => 'gelb',
                'symbol' => 'calendar',
                'link' => route('admin.learners.show', $lernender->lernender_id),
            ];
        }

        foreach ($kritisch as $kr) {
            $eintraege[] = [
                'text' => $kr->lernender->benutzer->vorname.' '.$kr->lernender->benutzer->nachname,
                'meta' => $kr->stand->gruende ? implode(' · ', array_slice($kr->stand->gruende, 0, 2)) : null,
                'badge' => null,
                'note' => $kr->stand->semesterNote,
                'ton' => 'rot',
                'symbol' => 'user-circle',
                'link' => route('admin.learners.show', $kr->lernender->lernender_id),
            ];
        }

        return $eintraege;
    }

    /** Lernenden-Detail (Cockpit) für Admin und Berufsbildner. */
    public function lernendenDetail(Lernender $l, string $bereich, User $betrachter): array
    {
        $id = (int) $l->lernender_id;
        $stand = $this->lernstaende->fuer([$id])[$id];
        $a = $stand->auswertung;
        $pruefungen = $l->pruefungen()->offen()->with(['fach', 'modul'])->orderBy('datum')->get();

        return [
            'stand' => $stand,
            'heatmap' => $this->heatmap($a),
            'ziele' => $this->zieleMitBedarf($l, $a, fn (string $ziel, float $wert) => route($bereich.'.learners.calculator', ['lernender_id' => $id, 'ziel' => $ziel, 'zielwert' => $wert])),
            'pruefungen' => $pruefungen,
            // Modulstatus (Rückmeldung #14): Dauer seit Beginn, nächster/letzter Termin, bewerteter Anteil.
            'modulstatus' => collect($this->modulstatus->fuerLernenden($id))->keyBy('schluessel'),
            'letzteNoten' => $l->noten()->with(['fach', 'modulBelegung.modul'])->latest('pruefungsdatum')->latest('note_id')->limit(6)->get(),
            'neu' => $this->ungeseheneNoten([$id], (int) $betrachter->benutzer_id)[$id] ?? 0,
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
            'semester' => array_map(fn ($s) => ['id' => $s, 'name' => $k->semesterName($s, $a->lernenderId)], $semesterIds),
            'gruppen' => $gruppen,
            'semesterschnitt' => array_combine($semesterIds, array_map(fn ($s) => $a->semester($s)['note'], $semesterIds)) ?: [],
            'gesamt' => $a->gesamtNote,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function zieleMitBedarf(Lernender $l, Auswertung $a, ?\Closure $link = null): array
    {
        $link ??= fn (string $ziel, float $wert) => route('learner.grades.calculator', ['ziel' => $ziel, 'zielwert' => $wert]);
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
                'label' => $g->label($k, $a->lernenderId),
                'zielwert' => (float) $z->zielwert,
                'aktuell' => $a->wert($g),
                'loesung' => $loesung,
                'link' => $link((string) $g, (float) $z->zielwert),
            ];
        })->all();
    }

    /** @return list<array{text: string, detail: ?string, link: string, ton: string}> */
    private function zuTunLernender(Collection $ueberfaellig, int $kommentare, ?int $ersteNoteMitKommentar, array $fehlendeModule, Lernstand $stand, Auswertung $a): array
    {
        $liste = [];
        foreach ($ueberfaellig as $p) {
            $liste[] = ['text' => __('Note eintragen: :bezeichnung', ['bezeichnung' => $p->bezeichnung()]),
                'detail' => __('Prüfung vom :datum', ['datum' => $p->datum->format('d.m.')]),
                'link' => route('learner.grades.create', ['pruefung' => $p->pruefung_id]), 'ton' => 'gelb'];
        }
        foreach ($stand->promotion as $p) {
            $kid = $p['kategorie_id'];
            $liste[] = ['text' => __('Promotion :kategorie gefährdet', ['kategorie' => $p['kategorie']]), 'detail' => $a->konfiguration->semesterName($stand->semesterId, $a->lernenderId),
                'link' => route('learner.grades.calculator', ['ziel' => 'kategorie:'.$kid.'@semester:'.$stand->semesterId, 'zielwert' => $a->konfiguration->kategorien[$kid]['promotion_min_schnitt'] ?? $a->konfiguration->genuegend]),
                'ton' => 'rot'];
        }
        if ($kommentare > 0) {
            $liste[] = ['text' => $kommentare === 1 ? __('1 Note mit neuem Kommentar') : __(':anzahl Noten mit neuen Kommentaren', ['anzahl' => $kommentare]), 'detail' => null,
                'link' => route('learner.grades.index', ['_open' => $ersteNoteMitKommentar]), 'ton' => 'accent'];
        }
        foreach (array_slice($fehlendeModule, 0, 4) as $m) {
            $liste[] = ['text' => $m['name'], 'detail' => __('noch keine Note'), 'link' => route('learner.grades.create', ['bezug' => 'modul:'.$m['id']]), 'ton' => 'neutral'];
        }

        return $liste;
    }

    private function verlaufDiagramm(Auswertung $a, array $semesterIds): array
    {
        $k = $a->konfiguration;
        $serien = [['name' => __('Semesterschnitt'), 'werte' => array_map(fn ($s) => $a->semester($s)['note'], $semesterIds), 'farbe' => '--accent', 'dick' => true]];
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

        return ['labels' => array_map(fn ($s) => $k->semesterName($s, $a->lernenderId), $semesterIds), 'serien' => $serien, 'faecher' => array_values($faecher), 'grenze' => $k->genuegend];
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
            'semester' => ['name' => $a->konfiguration->semesterName($semesterId, $a->lernenderId), 'labels' => array_map(fn (Element $e) => $e->label, $semester), 'werte' => array_map(fn (Element $e) => $e->note, $semester)],
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

        $k = Konfiguration::ausDb();
        $aktuellesSemesterId = $k->semesterFuerDatum(now()->toDateString());
        $aktuellesSemesterEnde = $aktuellesSemesterId !== null ? Carbon::parse($k->semester[$aktuellesSemesterId]['ende']) : null;

        return [
            'beginn' => $l->lehrbeginn,
            'ende' => $l->lehrende,
            'prozent' => (int) max(0, min(100, round($vergangen / $gesamt * 100))),
            'lehrjahr' => $l->lehrjahr(),
            'tage' => (int) now()->startOfDay()->diffInDays($l->lehrende, false),
            'beruf' => $l->lehrberuf?->name,
            'semester_id' => $aktuellesSemesterId,
            'semester' => $aktuellesSemesterId !== null ? $k->semesterName($aktuellesSemesterId, (int) $l->lernender_id) : null,
            'semester_rest' => $aktuellesSemesterEnde ? Format::restdauer($aktuellesSemesterEnde) : null,
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
            ->where(Ungelesen::bedingung($viewerId, 'n', 'ng'))
            ->groupBy('n.lernender_id')
            ->selectRaw('n.lernender_id, COUNT(DISTINCT n.note_id) as anzahl')
            ->pluck('anzahl', 'lernender_id')
            ->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Wie ungeseheneNoten(), aber für mehrere Betrachter (je Berufsbildner seine eigene Sicht) in
     * einer Abfrage statt einer Abfrage pro Berufsbildner (Admin-Dashboard, Spalte "neu" je Berufsbildner).
     *
     * @param  list<int>  $berufsbildnerIds
     * @param  list<int>  $lernendeIds  nur diese (aktiven) Lernenden zählen
     * @return array<int, int> ungesehene Noten (Summe über die betreuten Lernenden) je berufsbildner_id
     */
    private function ungeseheneNotenProBb(array $berufsbildnerIds, array $lernendeIds): array
    {
        if ($berufsbildnerIds === [] || $lernendeIds === []) {
            return [];
        }

        $heute = now()->toDateString();

        return DB::table('betreuungen as bt')
            ->join('berufsbildner as bb', 'bb.berufsbildner_id', '=', 'bt.berufsbildner_id')
            ->join('noten as n', fn ($j) => $j->on('n.lernender_id', '=', 'bt.lernender_id')->whereNull('n.geloescht_am'))
            ->leftJoin('noten_gesehen as ng', fn ($j) => $j->on('ng.note_id', '=', 'n.note_id')->on('ng.viewer_benutzer_id', '=', 'bb.benutzer_id'))
            ->whereIn('bt.berufsbildner_id', $berufsbildnerIds)->whereIn('bt.lernender_id', $lernendeIds)
            ->where('bt.gueltig_von', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('bt.gueltig_bis')->orWhere('bt.gueltig_bis', '>=', $heute))
            ->where(Ungelesen::bedingung('bb.benutzer_id', 'n', 'ng'))
            ->groupBy('bt.berufsbildner_id')
            ->selectRaw('bt.berufsbildner_id, COUNT(DISTINCT n.note_id) as anzahl')
            ->pluck('anzahl', 'berufsbildner_id')
            ->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Sortierung der Klassentabelle: ohne (gültigen) Sortierschlüssel Status dann Nachname,
     * sonst nach gewählter Spalte und Richtung; Zeilen ohne Wert immer am Schluss.
     */
    private function bbSortiert(Collection $zeilen, ?string $sort, string $dir): Collection
    {
        $spalten = [
            'name' => fn (object $z) => mb_strtolower($z->lernender->benutzer->nachname.' '.$z->lernender->benutzer->vorname),
            'status' => fn (object $z) => $z->stand->rang(),
            'semester' => fn (object $z) => $z->stand->semesterNote,
            'gesamt' => fn (object $z) => $z->stand->auswertung->gesamtNote,
            'trend' => fn (object $z) => $z->stand->delta(),
        ];

        if ($sort === null || ! isset($spalten[$sort])) {
            return $zeilen->sortBy([
                fn ($a, $b) => $a->stand->rang() <=> $b->stand->rang(),
                fn ($a, $b) => strcoll($a->lernender->benutzer->nachname, $b->lernender->benutzer->nachname),
            ])->values();
        }

        $wert = $spalten[$sort];
        $richtung = $dir === 'desc' ? -1 : 1;

        return $zeilen->sort(function (object $a, object $b) use ($wert, $richtung) {
            $va = $wert($a);
            $vb = $wert($b);
            if ($va === null || $vb === null) {
                return $va === $vb ? 0 : ($va === null ? 1 : -1);
            }

            return (is_string($va) ? strcoll($va, $vb) : $va <=> $vb) * $richtung;
        })->values();
    }

    /**
     * Lernende mit Status rot/gelb oder neuen Noten, mit den Gründen als Text.
     *
     * @return list<array{zeile: object, gruende: list<string>}>
     */
    private function bbAufmerksamkeit(Collection $zeilen): array
    {
        return $zeilen->filter(fn ($z) => in_array($z->stand->status, [Lernstand::ROT, Lernstand::GELB], true) || $z->neu > 0)
            ->map(fn ($z) => ['zeile' => $z, 'gruende' => $this->bbGruende($z)])
            ->values()->all();
    }

    /** @return list<string> */
    private function bbGruende(object $z): array
    {
        $gruende = $z->stand->gruende;
        if ($z->neu > 0) {
            $gruende[] = $z->neu === 1 ? __('1 neue Note') : __(':anzahl neue Noten', ['anzahl' => $z->neu]);
        }

        return $gruende;
    }

    /** Prüfungen der nächsten 14 Tage nach Tag gruppiert. @return Collection<string, Collection<int, Pruefung>> */
    private function bbAgenda(Collection $pruefungen): Collection
    {
        return $pruefungen->groupBy(fn (Pruefung $p) => $p->datum->toDateString());
    }

    private function lehrendeBald(Collection $lernende): Collection
    {
        $frist = Betrieb::fristLehrendeTage();

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
            ['text' => __('Lernende ohne aktive Betreuung'), 'anzahl' => $ohneBetreuung, 'link' => route('admin.learners.index', ['warnung' => 'ohne_betreuung'])],
            ['text' => __('Lernende ohne aktiven Track'), 'anzahl' => $ohneTrack, 'link' => route('admin.learners.index', ['warnung' => 'ohne_track'])],
            ['text' => __('Module ohne Lernort'), 'anzahl' => DB::table('lehrberuf_module')->where('aktiv', 1)->whereNull('kategorie_id')->count(), 'link' => route('admin.master-data.professions.index')],
            ['text' => __('Fächer ohne Kategorie'), 'anzahl' => DB::table('faecher')->where('aktiv', 1)->whereNull('kategorie_id')->count(), 'link' => route('admin.master-data.subjects.index')],
            ['text' => __('Lehrberufe ohne Module'), 'anzahl' => DB::table('lehrberufe as lb')->where('lb.aktiv', 1)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('lehrberuf_module as m')->whereColumn('m.lehrberuf_id', 'lb.lehrberuf_id'))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('lehrberuf_faecher as f')->whereColumn('f.lehrberuf_id', 'lb.lehrberuf_id'))->count(),
                'link' => route('admin.master-data.professions.index')],
            ['text' => $semesterReichtBis ? __('Semester erfasst bis :datum', ['datum' => $semesterReichtBis->format('d.m.Y')]) : __('Keine Semester erfasst'),
                'anzahl' => ! $semesterReichtBis || $semesterReichtBis->lt(now()->addMonths(6)) ? 1 : 0, 'link' => route('admin.master-data.semesters.index')],
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
            $labels[] = __('KW :nr', ['nr' => $w->isoWeek()]);
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
            'labels' => array_map(fn (int $j) => __(':jahr. Lehrjahr', ['jahr' => $j]), [1, 2, 3, 4]),
            'serien' => array_map(fn ($beruf, $jahre) => ['name' => $beruf, 'werte' => array_map(
                fn ($j) => isset($jahre[$j]) ? round(Rundung::mittel($jahre[$j]), 2) : null, [1, 2, 3, 4])], array_keys($gruppen), $gruppen),
        ];
    }
}
