<?php

namespace App\Services\Noten;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Models\Semester;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * app/Services/Noten/NoteService.php
 *
 * Zweck:
 * - Zentrale Logik rund um Noten (Queries, Filter, Form-Optionen, Validierung)
 * - Bausteine für Lernender/Berufsbildner/Admin ohne Logik 3x zu kopieren
 *
 * Wichtige Regeln:
 * - Lernender sieht im GUI keine "modul_belegungen", sondern wählt ein Modul (modul_id).
 * - Die App:
 *   - prüft: Modul gehört zum Lehrberuf (lehrberuf_module)
 *   - findet offene Belegung (end_datum IS NULL) oder erstellt sie automatisch
 * - semester_id wird immer automatisch via pruefungsdatum ermittelt
 * - gewichtung_prozent: wenn leer -> Default 100.00
 * - Fächer-Auswahl (faecher): Fächer aller bisherigen Tracks (lernender_tracks); gespeichert wird ein
 *   Track-Fach nur, wenn der Track am Prüfungsdatum gültig war (kategorieFuer mit Stichtag)
 */
class NoteService
{
    /**
     * Caches innerhalb einer Service-Instanz: pruefeZeile() läuft beim Notenimport pro Zeile, bei mehreren
     * hundert Zeilen sonst mehrere tausend Mal dieselben Abfragen (Lernender, Semester, erlaubte
     * Fächer/Kategorien) – analog zu fachErlaubtAm() einmal laden statt pro Zeile. Aktiv nur zwischen
     * beginBatch()/endBatch() (siehe NotenImport::vorschau/pruefeZeilen/importieren): eine einzelne
     * Handerfassung (NotenController::store/update) resetet den Cache bei jedem Aufruf, damit eine
     * ausserhalb eines Imports wiederverwendete Service-Instanz (z. B. injizierte Controller-Instanz)
     * nie veraltete Daten liefert.
     */
    private array $lernenderCache = [];

    private array $semesterCache = [];

    /** @var array<string, Collection> kategorie_id je fach_id, Schlüssel "lernenderId|stichtag" */
    private array $erlaubteFaecherKategorienCache = [];

    /** @var array<int, Collection> kategorie_id je modul_id, Schlüssel lehrberuf_id */
    private array $modulKategorienCache = [];

    private bool $batch = false;

    /** Beginn eines Bulk-Vorgangs (Notenimport): Cache wird bis endBatch() über mehrere Zeilen hinweg wiederverwendet. */
    public function beginBatch(): void
    {
        $this->resetCache();
        $this->batch = true;
    }

    /** Ende eines Bulk-Vorgangs: Cache wird verworfen, damit spätere Einzelaufrufe wieder frische Daten lesen. */
    public function endBatch(): void
    {
        $this->batch = false;
        $this->resetCache();
    }

    private function resetCache(): void
    {
        $this->lernenderCache = [];
        $this->semesterCache = [];
        $this->erlaubteFaecherKategorienCache = [];
        $this->modulKategorienCache = [];
    }

    /**
     * Basis-Query für Noten eines Lernenden (inkl. Relations).
     */
    public function learnerNotesQuery(int $lernenderId): Builder
    {
        return Note::query()
            ->with(['kategorie', 'semester', 'fach', 'modulBelegung.modul'])
            ->where('lernender_id', $lernenderId)
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id');
    }

    /**
     * Standard-Filter für Index-Seite.
     */
    public function applyIndexFilters(Builder $q, ?int $kategorieId, ?int $semesterId): Builder
    {
        if (! empty($kategorieId)) {
            $q->where('kategorie_id', $kategorieId);
        }

        if (! empty($semesterId)) {
            $q->where('semester_id', $semesterId);
        }

        return $q;
    }

    /**
     * Daten für Create/Edit-Form eines Lernenden.
     *
     * Wichtig:
     * - Modul-Auswahl ist eine Modul-Liste (module), NICHT modul_belegungen.
     * - Gruppen lassen wir für Lernende vorerst raus (gruppe_id bleibt null),
     *   weil Gruppen an Belegungen hängen und Belegungen "unsichtbar" sein sollen.
     * - Fächer: alle bisherigen Tracks (auch beendete, z.B. alte BM-Noten); die Gültigkeit am
     *   Prüfungsdatum prüft erst das Speichern (normalizeForSave → kategorieFuer).
     */
    public function formOptionsForLernender(int $lernenderId): array
    {
        $kategorien = Kategorie::query()->where('aktiv', 1)->orderBy('sortierung')->get();

        $faecher = $this->auswahlFaecher($lernenderId)->with('kategorie')->orderBy('name')->get();

        // Modul-Liste nach Lehrberuf (mit "has_open_belegung" Flag & Sortierung)
        $module = $this->modulesForLernender($lernenderId);

        // Semester-Liste begrenzen (ab Lehrbeginn)
        $semester = $this->semestersForLernender($lernenderId);

        return compact('kategorien', 'faecher', 'module', 'semester');
    }

    /**
     * Fächer, die ein Lernender am Stichtag erfassen darf: Track-Fächer (BMS/ABU) bei am Stichtag gültigem Track,
     * Fächer ohne Track über die Freigabe im Lehrberuf (lehrberuf_faecher, unabhängig vom Datum).
     * Ohne Stichtag: heute (Rechner, Listen).
     */
    public function erlaubteFaecher(int $lernenderId, ?CarbonInterface $stichtag = null): Builder
    {
        return $this->faecherFuerTracks($lernenderId, $this->activeTrackTypesForLernender($lernenderId, $stichtag)->all());
    }

    /**
     * Fächer für Auswahllisten (Formular, Drawer, Import, Agenda): wie erlaubteFaecher, aber mit allen Tracks,
     * die bis heute begonnen haben – auch beendeten. Ob der Track am Prüfungsdatum galt, prüft kategorieFuer.
     */
    public function auswahlFaecher(int $lernenderId): Builder
    {
        $tracks = DB::table('lernender_tracks')
            ->where('lernender_id', $lernenderId)
            ->where('start_datum', '<=', now()->toDateString())
            ->distinct()
            ->pluck('track_typ')
            ->all();

        return $this->faecherFuerTracks($lernenderId, $tracks);
    }

    /**
     * Prüfer «Fach am Datum erlaubt?» für viele Daten (Import-Vorschau): Tracks und Fächer werden einmal geladen,
     * gleiche Regel wie erlaubteFaecher($id, $datum).
     *
     * @return \Closure(string, int): bool
     */
    public function fachErlaubtAm(int $lernenderId): \Closure
    {
        $tracks = DB::table('lernender_tracks')->where('lernender_id', $lernenderId)->get(['track_typ', 'start_datum', 'end_datum']);
        $faecher = $this->faecherFuerTracks($lernenderId, $tracks->pluck('track_typ')->unique()->values()->all())
            ->pluck('track_typ', 'fach_id');

        return function (string $datum, int $fachId) use ($tracks, $faecher): bool {
            if (! $faecher->has($fachId)) {
                return false;
            }
            $typ = $faecher[$fachId];
            $tag = Carbon::parse($datum)->toDateString();

            return $typ === null || $tracks->contains(fn ($t) => $t->track_typ === $typ
                && (string) $t->start_datum <= $tag && ($t->end_datum === null || (string) $t->end_datum >= $tag));
        };
    }

    /** @param  list<string>  $tracks */
    private function faecherFuerTracks(int $lernenderId, array $tracks): Builder
    {
        $lehrberufId = $this->lehrberufIdFuer($lernenderId);

        return Fach::query()
            ->where('aktiv', 1)
            ->whereNotNull('kategorie_id')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $t) => $t->whereNotNull('track_typ')->whereIn('track_typ', $tracks === [] ? [''] : $tracks))
                ->orWhere(fn (Builder $b) => $b->whereNull('track_typ')->whereIn('fach_id', DB::table('lehrberuf_faecher')
                    ->where('lehrberuf_id', $lehrberufId)->where('aktiv', 1)->select('fach_id'))));
    }

    /**
     * Aktive Track-Typen des Lernenden am Stichtag (Standard: heute).
     * Es können theoretisch mehrere aktiv sein (z.B. ABU + BMS parallel).
     *
     * Aktiv = start_datum <= Stichtag AND (end_datum IS NULL OR end_datum >= Stichtag)
     */
    public function activeTrackTypesForLernender(int $lernenderId, ?CarbonInterface $stichtag = null): Collection
    {
        $today = ($stichtag ?? now())->toDateString();

        return DB::table('lernender_tracks')
            ->where('lernender_id', $lernenderId)
            ->where('start_datum', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_datum')->orWhere('end_datum', '>=', $today);
            })
            ->distinct()
            ->pluck('track_typ');
    }

    /**
     * Liefert Semester, die für einen Lernenden relevant sind:
     * - ab lehrbeginn
     * - bis lehrende (falls gesetzt), sonst bis heute
     */
    public function semestersForLernender(int $lernenderId): Collection
    {
        $l = Lernender::query()
            ->select(['lernender_id', 'lehrbeginn', 'lehrende'])
            ->where('lernender_id', $lernenderId)
            ->first();

        // Kein Lernender oder kein Lehrbeginn → leere Liste (verhindert Crash)
        if (! $l || ! $l->lehrbeginn) {
            return collect();
        }

        $from = (string) $l->lehrbeginn;
        $to = $l->lehrende ? (string) $l->lehrende : now()->toDateString();

        return Semester::query()
            ->where('end_datum', '>=', $from)
            ->where('start_datum', '<=', $to)
            ->orderBy('sortierung')
            ->get();
    }

    /**
     * Modul-Liste für Lernenden:
     * - nur Module, die im Lehrberuf des Lernenden aktiv sind (lehrberuf_module.aktiv=1)
     * - nur aktive Module (module.aktiv=1)
     * - Sortierung:
     *   1) Module mit offener Belegung (end_datum IS NULL) zuerst
     *   2) dann modul_nummer, titel
     */
    public function modulesForLernender(int $lernenderId): Collection
    {
        $lernender = Lernender::query()
            ->select(['lernender_id', 'lehrberuf_id'])
            ->where('lernender_id', $lernenderId)
            ->first();

        if (! $lernender) {
            return collect();
        }

        $open = DB::table('modul_belegungen as mb')
            ->select([
                'mb.modul_id',
                DB::raw('MAX(mb.modul_belegung_id) as open_modul_belegung_id'),
            ])
            ->where('mb.lernender_id', $lernenderId)
            ->whereNull('mb.end_datum')
            ->groupBy('mb.modul_id');

        return DB::table('lehrberuf_module as lbm')
            ->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
            ->leftJoinSub($open, 'openmb', function ($join) {
                $join->on('openmb.modul_id', '=', 'm.modul_id');
            })
            ->where('lbm.lehrberuf_id', (int) $lernender->lehrberuf_id)
            ->where('lbm.aktiv', 1)
            ->where('m.aktiv', 1)
            ->select([
                'm.modul_id',
                'm.modul_nummer',
                'm.titel',
                'lbm.kategorie_id',
                'm.ziel_gewicht_summe_default',
                DB::raw('CASE WHEN openmb.open_modul_belegung_id IS NULL THEN 0 ELSE 1 END as has_open_belegung'),
                'openmb.open_modul_belegung_id',
            ])
            ->orderByDesc('has_open_belegung')
            ->orderBy('m.modul_nummer')
            ->orderBy('m.titel')
            ->get();
    }

    /**
     * Prüft/normalisiert Input-Daten ohne zu schreiben – einzige Regelquelle für Handerfassung und Import
     * (NotenImport::vorschau/importieren rufen dieselbe Methode für die Vorschau bzw. vor dem Speichern auf):
     * - Lernender vorhanden, Prüfungsdatum innerhalb Lehrbeginn/-ende
     * - Semester am Prüfungsdatum vorhanden -> semester_id
     * - Typ-Logik (fach vs modul)
     * - Fach: fach_id muss zu einem am Prüfungsdatum gültigen Track (oder zum Lehrberuf) gehören -> kategorie_id
     * - Modul: modul_id muss zum Lehrberuf gehören (lehrberuf_module) -> kategorie_id
     * - gewichtung_prozent Default = 100.00
     *
     * modul_belegung_id ist hier immer null: die offene Belegung zu finden/anzulegen schreibt (siehe
     * normalizeForSave), eine reine Prüfung darf das nicht.
     */
    public function pruefeZeile(array $data, int $lernenderId): array
    {
        if (! $this->batch) {
            $this->resetCache();
        }

        $lernender = $this->lernenderFuer($lernenderId);

        if (! $lernender) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => __('Lernender nicht gefunden.'),
            ]);
        }

        $date = (string) $data['pruefungsdatum'];

        if ($date < (string) $lernender->lehrbeginn) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => __('Das Prüfungsdatum liegt vor dem Lehrbeginn.'),
            ]);
        }

        if ($lernender->lehrende && $date > (string) $lernender->lehrende) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => __('Das Prüfungsdatum liegt nach dem Lehrende.'),
            ]);
        }

        $semester = $this->semesterForDate($date);
        if (! $semester) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => __('Kein Semester gefunden, das dieses Datum abdeckt.'),
            ]);
        }

        $gewicht = $data['gewichtung_prozent'] ?? null;
        if ($gewicht === null || $gewicht === '') {
            $gewicht = 100.00;
        }

        $typ = $data['typ'] ?? null;

        if ($typ === 'fach') {
            if (empty($data['fach_id'])) {
                throw ValidationException::withMessages([
                    'fach_id' => __('Bitte ein Fach wählen.'),
                ]);
            }

            $fachId = (int) $data['fach_id'];
            $kategorieId = $this->kategorieFuer($lernenderId, 'fach', $fachId, Carbon::parse($date));

            return [
                'kategorie_id' => (int) $kategorieId,
                'semester_id' => (int) $semester->semester_id,
                'fach_id' => $fachId,
                'modul_id' => null,
                'modul_belegung_id' => null,
                'titel' => $data['titel'] ?? null,
                'pruefungsdatum' => $date,
                'note_wert' => $data['note_wert'],
                'gewichtung_prozent' => (float) $gewicht,
            ];
        }

        if ($typ === 'modul') {
            if (empty($data['modul_id'])) {
                throw ValidationException::withMessages([
                    'modul_id' => __('Bitte ein Modul wählen.'),
                ]);
            }

            $modulId = (int) $data['modul_id'];
            $kategorieId = $this->kategorieFuer($lernenderId, 'modul', $modulId);

            return [
                'kategorie_id' => (int) $kategorieId,
                'semester_id' => (int) $semester->semester_id,
                'fach_id' => null,
                'modul_id' => $modulId,
                'modul_belegung_id' => null,
                'titel' => $data['titel'] ?? null,
                'pruefungsdatum' => $date,
                'note_wert' => $data['note_wert'],
                'gewichtung_prozent' => (float) $gewicht,
            ];
        }

        throw ValidationException::withMessages([
            'typ' => __('Ungültiger Typ.'),
        ]);
    }

    /**
     * Prüft wie pruefeZeile() und legt bei einem Modul zusätzlich die offene Modulbelegung an/findet sie
     * (einziger schreibende Schritt) -> modul_belegung_id setzen.
     */
    public function normalizeForSave(array $data, int $lernenderId): array
    {
        $ergebnis = $this->pruefeZeile($data, $lernenderId);

        if (($data['typ'] ?? null) === 'modul') {
            $ergebnis['modul_belegung_id'] = $this->resolveOrCreateOpenModulBelegung($lernenderId, (int) $ergebnis['modul_id'], $ergebnis['pruefungsdatum']);
        }
        unset($ergebnis['modul_id']);

        return $ergebnis;
    }

    /**
     * Einzige Zuordnungsregel für Noten und geplante Prüfungen: Fach muss für Beruf/Track freigegeben sein
     * (Kategorie folgt aus dem Fach), Modul muss zum Lehrberuf gehören (Kategorie = Lernort im Beruf).
     *
     * Track-Fächer gelten nur, wenn der Track am Stichtag (Prüfungsdatum; ohne Angabe heute) gültig war.
     *
     * @param  'fach'|'modul'  $typ
     */
    public function kategorieFuer(int $lernenderId, string $typ, int $id, ?CarbonInterface $stichtag = null): int
    {
        if ($typ === 'fach') {
            $kategorieId = $this->erlaubteFaecherKategorien($lernenderId, $stichtag)->get($id);

            if (! $kategorieId) {
                throw ValidationException::withMessages(['fach_id' => $this->trackNichtAktivMeldung($lernenderId, $id, $stichtag ?? now())
                    ?? __('Dieses Fach ist für den Lehrberuf oder Track nicht freigegeben.')]);
            }

            return (int) $kategorieId;
        }

        $kategorieId = $this->modulKategorien($this->lehrberufIdFuer($lernenderId))->get($id);

        if (! $kategorieId) {
            throw ValidationException::withMessages(['modul_id' => __('Dieses Modul gehört nicht zum Lehrberuf.')]);
        }

        return (int) $kategorieId;
    }

    /** kategorie_id je fach_id (erlaubteFaecher), pro Lernender+Stichtag einmal geladen statt pro Zeile. */
    private function erlaubteFaecherKategorien(int $lernenderId, ?CarbonInterface $stichtag): Collection
    {
        $key = $lernenderId.'|'.($stichtag?->toDateString() ?? '');

        return $this->erlaubteFaecherKategorienCache[$key] ??= $this->erlaubteFaecher($lernenderId, $stichtag)->pluck('kategorie_id', 'fach_id');
    }

    /** kategorie_id je modul_id (aktive lehrberuf_module), pro Lehrberuf einmal geladen statt pro Zeile. */
    private function modulKategorien(int $lehrberufId): Collection
    {
        return $this->modulKategorienCache[$lehrberufId] ??= DB::table('lehrberuf_module')
            ->where('lehrberuf_id', $lehrberufId)
            ->where('aktiv', 1)
            ->pluck('kategorie_id', 'modul_id');
    }

    /** Lehrberuf-ID eines Lernenden, aus dem gecachten Lernenden-Datensatz (pruefeZeile lädt ihn ohnehin). */
    private function lehrberufIdFuer(int $lernenderId): int
    {
        return (int) ($this->lernenderFuer($lernenderId)->lehrberuf_id ?? 0);
    }

    /** Lernender-Basisdaten (lehrbeginn, lehrende, lehrberuf_id), pro ID einmal geladen statt pro Zeile. */
    private function lernenderFuer(int $lernenderId): ?Lernender
    {
        if (! array_key_exists($lernenderId, $this->lernenderCache)) {
            $this->lernenderCache[$lernenderId] = Lernender::query()
                ->select(['lernender_id', 'lehrbeginn', 'lehrende', 'lehrberuf_id'])
                ->where('lernender_id', $lernenderId)
                ->first();
        }

        return $this->lernenderCache[$lernenderId];
    }

    /**
     * Meldung, wenn das Fach zu einem Track des Lernenden gehört, dieser am Stichtag aber nicht galt; sonst null.
     */
    private function trackNichtAktivMeldung(int $lernenderId, int $fachId, CarbonInterface $stichtag): ?string
    {
        $track = Fach::query()->whereKey($fachId)->value('track_typ');
        if (! $track || ! $this->faecherFuerTracks($lernenderId, [$track])->whereKey($fachId)->exists()) {
            return null;
        }

        $zeiten = DB::table('lernender_tracks')->where('lernender_id', $lernenderId)->where('track_typ', $track)
            ->orderBy('start_datum')->get(['start_datum', 'end_datum']);
        if ($zeiten->isEmpty()) {
            return null;
        }
        $zeitraum = $zeiten->map(fn ($t) => Carbon::parse($t->start_datum)->format('d.m.Y').' – '
            .($t->end_datum ? Carbon::parse($t->end_datum)->format('d.m.Y') : __('heute')))->implode(', ');

        return __('Der Track :track war am :datum nicht aktiv (Track-Zeit: :zeitraum). Fächer dieses Tracks brauchen ein Prüfungsdatum innerhalb der Track-Zeit.', [
            'track' => $track, 'datum' => $stichtag->format('d.m.Y'), 'zeitraum' => $zeitraum,
        ]);
    }

    /**
     * Alle Semester mit Zeitraum (Zuordnung Datum → Semester im Formular).
     *
     * @return list<array{id: int, name: string, start: string, ende: string}>
     */
    public function semesterListe(): array
    {
        return Semester::query()->orderBy('sortierung')->get()
            ->map(fn (Semester $s) => ['id' => (int) $s->semester_id, 'name' => $s->bezeichnung,
                'start' => $s->start_datum->toDateString(), 'ende' => $s->end_datum->toDateString()])
            ->all();
    }

    /**
     * Auswahl «Fach oder Modul» für Formulare, gruppiert nach Kategorie: [Kategoriename => [[wert, label]]].
     *
     * @return array<string, list<array{wert: string, label: string}>>
     */
    public function bezugOptionen(int $lernenderId): array
    {
        $optionen = $this->formOptionsForLernender($lernenderId);
        $namen = Kategorie::query()->pluck('name', 'kategorie_id');
        $gruppen = [];

        foreach ($optionen['module'] as $m) {
            $gruppen[$namen[$m->kategorie_id] ?? 'Module'][] = ['wert' => 'modul:'.$m->modul_id, 'label' => trim($m->modul_nummer.' '.$m->titel)];
        }
        foreach ($optionen['faecher'] as $f) {
            $gruppen[$namen[$f->kategorie_id] ?? 'Fächer'][] = ['wert' => 'fach:'.$f->fach_id, 'label' => $f->name];
        }

        $reihenfolge = Kategorie::query()->orderBy('sortierung')->pluck('name')->all();
        uksort($gruppen, fn ($a, $b) => array_search($a, $reihenfolge, true) <=> array_search($b, $reihenfolge, true));

        return $gruppen;
    }

    /**
     * Validierungsfehler aus dem Notendrawer (Erfassen/Bearbeiten): Formular mit alter Eingabe
     * wieder im Drawer anzeigen – unabhängig davon, von welcher Seite der Drawer geöffnet wurde.
     *
     * @return array{titel: string, daten: array<string, mixed>}|null
     */
    public function drawerNachFehler(Request $request, Lernender $lernender): ?array
    {
        $kontext = (string) $request->old('_drawer', '');
        if ($kontext === '') {
            return null;
        }

        $daten = [
            'bezugOptionen' => $this->bezugOptionen((int) $lernender->lernender_id),
            'semesterListe' => $this->semesterListe(),
            'drawer' => $kontext,
        ];
        if ($kontext === 'neu') {
            return ['titel' => __('Neue Note'), 'daten' => $daten];
        }
        if (preg_match('/^bearbeiten:(\d+)$/', $kontext, $m)) {
            $note = Note::query()
                ->with(['fach', 'modulBelegung.modul'])
                ->where('note_id', (int) $m[1])
                ->where('lernender_id', (int) $lernender->lernender_id)
                ->first();

            return $note ? ['titel' => __('Note bearbeiten'), 'daten' => $daten + ['note' => $note]] : null;
        }

        return null;
    }

    /**
     * Findet eine offene Modul-Belegung (end_datum IS NULL) oder erstellt sie.
     * start_datum setzen wir auf das Prüfungsdatum der ersten Note.
     */
    public function resolveOrCreateOpenModulBelegung(int $lernenderId, int $modulId, string $startDatum): int
    {
        return (int) DB::transaction(function () use ($lernenderId, $modulId, $startDatum) {
            $existing = ModulBelegung::query()
                ->where('lernender_id', $lernenderId)
                ->where('modul_id', $modulId)
                ->whereNull('end_datum')
                ->lockForUpdate()
                ->orderByDesc('start_datum')
                ->first();

            if ($existing) {
                // optional: start_datum früher setzen, falls neue Note früher ist
                if ((string) $existing->start_datum > $startDatum) {
                    $existing->start_datum = $startDatum;
                    $existing->save();
                }

                return (int) $existing->modul_belegung_id;
            }

            $created = ModulBelegung::create([
                'lernender_id' => $lernenderId,
                'modul_id' => $modulId,
                'start_datum' => $startDatum,
                'end_datum' => null,
            ]);

            return (int) $created->modul_belegung_id;
        });
    }

    /**
     * Semester anhand Datum finden.
     */
    public function semesterForDate(string $date): ?Semester
    {
        if (! array_key_exists($date, $this->semesterCache)) {
            $this->semesterCache[$date] = Semester::query()
                ->where('start_datum', '<=', $date)
                ->where('end_datum', '>=', $date)
                ->first();
        }

        return $this->semesterCache[$date];
    }
}
