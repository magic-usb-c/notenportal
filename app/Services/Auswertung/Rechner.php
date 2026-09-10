<?php

declare(strict_types=1);

namespace App\Services\Auswertung;

use App\Models\Lernender;
use App\Models\Ziel;
use App\Services\Noten\NoteService;
use App\Support\NotenSkala;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Noten-Rechner für einen Lernenden: Katalog (was man wählen kann), Vorschläge für offene Prüfungen,
 * Rückwärts- und Was-wäre-wenn-Rechnung über den Rechenkern.
 */
final class Rechner
{
    public function __construct(
        private readonly NotenQuelle $quelle,
        private readonly NoteService $noteService,
        private readonly Rechenkern $kern = new Rechenkern,
        private readonly Zielrechner $zielrechner = new Zielrechner,
    ) {}

    /** @return array<string, list<string>> */
    public static function regeln(): array
    {
        return [
            'ziel' => ['required', 'string', 'max:40'],
            'zielwert' => ['required', 'numeric', 'min:1', 'max:6'],
            'ersetzt' => ['nullable', 'integer'],
            'zeilen' => ['present', 'array', 'max:40'],
            'zeilen.*.element' => ['required', 'string', 'max:40'],
            'zeilen.*.gewicht' => ['required', 'numeric', 'min:0', 'max:100'],
            'zeilen.*.wert' => ['nullable', 'numeric', 'min:1', 'max:6'],
            'zeilen.*.datum' => ['nullable', 'date'],
        ];
    }

    /** Alles, was die Rechner-Seite beim Laden braucht. */
    public function seite(Lernender $lernender, bool $mitZielen): array
    {
        $a = $this->quelle->auswertung((int) $lernender->lernender_id);

        return [
            'katalog' => $this->katalog($lernender),
            'vorschlaege' => $this->vorschlaege($lernender, $a),
            'auswertung' => $a->toArray(),
            'ziele' => $mitZielen ? $this->ziele($lernender) : [],
            'grenzen' => NotenSkala::grenzen(),
        ];
    }

    /** @return list<array{id: int, ziel: string, label: string, zielwert: float}> */
    public function ziele(Lernender $lernender): array
    {
        $k = Konfiguration::ausDb();

        return Ziel::query()->where('lernender_id', $lernender->lernender_id)->orderBy('ziel_id')->get()
            ->map(fn (Ziel $z) => [
                'id' => (int) $z->ziel_id,
                'ziel' => (string) $z->zielgroesse(),
                'label' => $z->zielgroesse()->label($k),
                'zielwert' => (float) $z->zielwert,
            ])->all();
    }

    /**
     * Fächer (erfassbar oder mit Noten), Module des Lehrberufs, Semester der Lehrzeit.
     *
     * @return array{faecher: list<array<string, mixed>>, module: list<array<string, mixed>>, semester: list<array<string, mixed>>, kategorien: list<array{id: int, name: string}>, aktuelles_semester: ?int}
     */
    public function katalog(Lernender $lernender): array
    {
        $k = Konfiguration::ausDb();
        $id = (int) $lernender->lernender_id;

        $erlaubt = $this->noteService->erlaubteFaecher($id)->pluck('kategorie_id', 'fach_id');
        $mitNoten = DB::table('noten as n')->join('faecher as f', 'f.fach_id', '=', 'n.fach_id')
            ->where('n.lernender_id', $id)->whereNull('n.geloescht_am')->whereNotNull('f.kategorie_id')
            ->distinct()->pluck('f.kategorie_id', 'f.fach_id');

        $faecher = $erlaubt->union($mitNoten)
            ->map(fn ($kid, $fid) => ['id' => (int) $fid, 'name' => $k->fachName((int) $fid), 'kategorie_id' => (int) $kid, 'erfassbar' => $erlaubt->has($fid)])
            ->sortBy('name')->values()->all();

        $module = DB::table('lehrberuf_module as lbm')
            ->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->where('lbm.lehrberuf_id', (int) $lernender->lehrberuf_id)
            ->where('lbm.aktiv', 1)->where('m.aktiv', 1)->whereNotNull('lbm.kategorie_id')
            ->orderBy('m.modul_nummer')
            ->get(['m.modul_id', 'lbm.kategorie_id', 'lbm.empfohlenes_lehrsemester_nr'])
            ->map(fn ($m) => [
                'id' => (int) $m->modul_id,
                'name' => $k->modulName((int) $m->modul_id),
                'kategorie_id' => (int) $m->kategorie_id,
                'ziel' => $k->module[(int) $m->modul_id]['ziel'] ?? null,
                'empfohlen' => $m->empfohlenes_lehrsemester_nr !== null ? (int) $m->empfohlenes_lehrsemester_nr : null,
            ])->all();

        $von = $lernender->lehrbeginn?->toDateString() ?? now()->toDateString();
        $bis = $lernender->lehrende?->toDateString() ?? Carbon::parse($von)->addYears(4)->toDateString();
        $semester = [];
        foreach ($k->semester as $sid => $s) {
            if ($s['ende'] >= $von && $s['start'] <= $bis) {
                $semester[] = ['id' => $sid, 'name' => $s['bezeichnung'], 'start' => $s['start'], 'ende' => $s['ende']];
            }
        }

        $kategorieIds = array_unique([...array_column($faecher, 'kategorie_id'), ...array_column($module, 'kategorie_id')]);
        $kategorien = [];
        foreach ($k->kategorien as $kid => $regel) {
            if (in_array($kid, $kategorieIds, true)) {
                $kategorien[] = ['id' => $kid, 'name' => $regel['name']];
            }
        }

        return [
            'faecher' => $faecher,
            'module' => $module,
            'semester' => $semester,
            'kategorien' => $kategorien,
            'aktuelles_semester' => $k->semesterFuerDatum(now()->toDateString()),
        ];
    }

    /**
     * Offene Prüfungen, die der Rechner vorschlägt: geplante Prüfungen und Restgewicht begonnener Module.
     *
     * @return list<array<string, mixed>>
     */
    public function vorschlaege(Lernender $lernender, Auswertung $a): array
    {
        $id = (int) $lernender->lernender_id;
        $zeilen = [];
        $geplantProModul = [];

        foreach ($this->quelle->geplante([$id])[$id] ?? [] as $p) {
            if ($p->fachId !== null) {
                $sem = $a->konfiguration->semesterFuerDatum((string) $p->datum);
                if ($sem === null) {
                    continue;
                }
                $element = "fach:{$p->fachId}@semester:{$sem}";
            } else {
                $element = "modul:{$p->modulId}";
                $geplantProModul[$p->modulId] = ($geplantProModul[$p->modulId] ?? 0) + $p->gewicht;
            }
            $zeilen[] = ['element' => $element, 'gewicht' => $p->gewicht, 'wert' => null, 'datum' => $p->datum,
                'titel' => $p->titel, 'quelle' => Leistung::GEPLANT];
        }

        foreach ($a->elemente as $e) {
            if ($e->typ !== Element::MODUL || $e->offenGewicht() === null || $e->abgeschlossen()) {
                continue;
            }
            $rest = $e->offenGewicht() - ($geplantProModul[$e->modulId] ?? 0);
            if ($rest > 0.001) {
                $zeilen[] = ['element' => "modul:{$e->modulId}", 'gewicht' => round($rest, 2), 'wert' => null, 'datum' => null,
                    'titel' => null, 'quelle' => 'rest'];
            }
        }

        return $zeilen;
    }

    /**
     * Vorgeschlagene offene Prüfungen als unbekannte Leistungen (für Ziele auf dem Dashboard).
     *
     * @return list<Leistung>
     */
    public function offeneLeistungen(Lernender $lernender, Auswertung $a): array
    {
        $katalog = $this->katalog($lernender);

        return array_map(fn (array $z) => $this->zeile($z, $katalog, $a->konfiguration, 'zeilen'), $this->vorschlaege($lernender, $a));
    }

    /** @param  array{ziel: string, zielwert: float|string, ersetzt?: ?int, zeilen: list<array<string, mixed>>}  $eingabe */
    public function berechne(Lernender $lernender, array $eingabe): array
    {
        $k = Konfiguration::ausDb();
        $ziel = $this->parse($eingabe['ziel'], 'ziel');
        $katalog = $this->katalog($lernender);

        $basis = $this->quelle->fuerLernenden((int) $lernender->lernender_id);
        if (! empty($eingabe['ersetzt'])) {
            $basis = array_values(array_filter($basis, fn (Leistung $l) => $l->id !== (int) $eingabe['ersetzt']));
        }

        $zeilen = [];
        foreach ($eingabe['zeilen'] as $i => $z) {
            $zeilen[] = $this->zeile($z, $katalog, $k, "zeilen.{$i}.element");
        }

        $alle = [...$basis, ...$zeilen];
        $zielwert = (float) $eingabe['zielwert'];
        $loesung = $this->zielrechner->loese($alle, $ziel, $zielwert, $k);

        $x = $loesung['status'] === Zielrechner::BENOETIGT ? $loesung['note'] : null;
        $vorher = $this->kern->auswerten($basis, $k);
        $nachher = $this->kern->auswerten($x !== null ? array_map(fn (Leistung $l) => $l->istUnbekannt() ? $l->mitWert($x) : $l, $alle) : $alle, $k);

        return [
            'ziel' => ['text' => (string) $ziel, 'label' => $ziel->label($k), 'zielwert' => $zielwert],
            'loesung' => $loesung,
            'kurve' => $loesung['unbekannte'] > 0 ? $this->zielrechner->kurve($alle, $ziel, $k) : [],
            'vergleich' => $this->vergleich($vorher, $nachher, $ziel, $zeilen),
            'promotion' => $this->promotionen($vorher, $nachher, $zeilen),
        ];
    }

    private function parse(string $text, string $feld): Zielgroesse
    {
        try {
            return Zielgroesse::parse($text);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$feld => 'Ungültige Auswahl.']);
        }
    }

    /** Eine Rechnerzeile als Leistung; nur Fächer/Module/Semester aus dem Katalog des Lernenden. */
    private function zeile(array $z, array $katalog, Konfiguration $k, string $feld): Leistung
    {
        $el = $this->parse((string) $z['element'], $feld);
        $wert = isset($z['wert']) && $z['wert'] !== '' ? (float) $z['wert'] : null;
        $gewicht = (float) $z['gewicht'];
        $datum = ! empty($z['datum']) ? Carbon::parse($z['datum'])->toDateString() : null;
        $quelle = $wert === null ? Leistung::GEPLANT : Leistung::ANNAHME;

        if ($el->ebene === 'fach' && $el->semesterId !== null
            && ($fach = collect($katalog['faecher'])->firstWhere('id', $el->id))
            && collect($katalog['semester'])->contains('id', $el->semesterId)) {
            return new Leistung($fach['kategorie_id'], $el->id, null, $el->semesterId, $datum, $wert, $gewicht, $quelle);
        }

        if ($el->ebene === 'modul' && ($modul = collect($katalog['module'])->firstWhere('id', $el->id))) {
            return new Leistung($modul['kategorie_id'], null, $el->id, $datum ? null : $katalog['aktuelles_semester'], $datum, $wert, $gewicht, $quelle);
        }

        throw ValidationException::withMessages([$feld => 'Ungültige Auswahl.']);
    }

    /**
     * Vorher/Nachher für das Ziel und alle Grössen, die sich durch die Zeilen ändern.
     *
     * @param  list<Leistung>  $zeilen
     * @return list<array<string, mixed>>
     */
    private function vergleich(Auswertung $vorher, Auswertung $nachher, Zielgroesse $ziel, array $zeilen): array
    {
        $groessen = [$ziel, new Zielgroesse('gesamt')];
        foreach (array_keys($nachher->kategorien) as $kid) {
            $groessen[] = new Zielgroesse('kategorie', $kid);
        }
        foreach ($zeilen as $z) {
            if ($z->fachId !== null) {
                $groessen[] = new Zielgroesse('fach', $z->fachId, $z->semesterId);
                $groessen[] = new Zielgroesse('fach', $z->fachId);
                $sem = $z->semesterId;
            } else {
                $groessen[] = new Zielgroesse('modul', $z->modulId);
                $sem = $nachher->elemente["m{$z->modulId}"]->semesterId ?? null;
            }
            if ($sem !== null) {
                $groessen[] = new Zielgroesse('semester', $sem);
            }
        }

        $gesehen = [];
        $out = [];
        foreach ($groessen as $g) {
            $text = (string) $g;
            if (isset($gesehen[$text])) {
                continue;
            }
            $gesehen[$text] = true;
            $v = $vorher->wert($g);
            $n = $nachher->wert($g);
            if ($g != $ziel && $v === $n) {
                continue;
            }
            $out[] = ['text' => $text, 'label' => $g->label($nachher->konfiguration), 'vorher' => $v, 'nachher' => $n, 'ist_ziel' => $g == $ziel];
        }

        return $out;
    }

    /**
     * Promotionsstand in den Semestern, die das Szenario berührt.
     *
     * @param  list<Leistung>  $zeilen
     * @return list<array<string, mixed>>
     */
    private function promotionen(Auswertung $vorher, Auswertung $nachher, array $zeilen): array
    {
        $semester = array_unique(array_filter(array_map(fn (Leistung $z) => $z->semesterId, $zeilen)));
        $out = [];
        foreach ($semester as $sid) {
            foreach (array_keys($nachher->kategorien) as $kid) {
                $p = $nachher->promotion($kid, $sid);
                if ($p !== null) {
                    $out[] = ['kategorie' => $nachher->konfiguration->kategorieName($kid), 'semester' => $nachher->konfiguration->semesterName($sid),
                        'vorher' => $vorher->promotion($kid, $sid), 'nachher' => $p];
                }
            }
        }

        return $out;
    }
}
