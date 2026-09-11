<?php

namespace App\Services\Noten;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
 * - Fächer-Auswahl (faecher) ist abhängig von lernender_tracks (aktive Tracks)
 */
class NoteService
{
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
     * - Fächer werden anhand der aktiven Tracks (lernender_tracks) gefiltert.
     */
    public function formOptionsForLernender(int $lernenderId): array
    {
        $kategorien = Kategorie::query()->where('aktiv', 1)->orderBy('sortierung')->get();

        $faecher = $this->erlaubteFaecher($lernenderId)->with('kategorie')->orderBy('name')->get();

        // Modul-Liste nach Lehrberuf (mit "has_open_belegung" Flag & Sortierung)
        $module = $this->modulesForLernender($lernenderId);

        // Semester-Liste begrenzen (ab Lehrbeginn)
        $semester = $this->semestersForLernender($lernenderId);

        return compact('kategorien', 'faecher', 'module', 'semester');
    }

    /**
     * Fächer, die ein Lernender erfassen darf: Track-Fächer (BMS/ABU) bei aktivem Track,
     * Fächer ohne Track über die Freigabe im Lehrberuf (lehrberuf_faecher).
     */
    public function erlaubteFaecher(int $lernenderId): Builder
    {
        $tracks = $this->activeTrackTypesForLernender($lernenderId)->all();
        $lehrberufId = (int) Lernender::query()->whereKey($lernenderId)->value('lehrberuf_id');

        return Fach::query()
            ->where('aktiv', 1)
            ->whereNotNull('kategorie_id')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $t) => $t->whereNotNull('track_typ')->whereIn('track_typ', $tracks === [] ? [''] : $tracks))
                ->orWhere(fn (Builder $b) => $b->whereNull('track_typ')->whereIn('fach_id', DB::table('lehrberuf_faecher')
                    ->where('lehrberuf_id', $lehrberufId)->where('aktiv', 1)->select('fach_id'))));
    }

    /**
     * Aktive Track-Typen des Lernenden am heutigen Datum.
     * Es können theoretisch mehrere aktiv sein (z.B. ABU + BMS parallel).
     *
     * Aktiv = start_datum <= today AND (end_datum IS NULL OR end_datum >= today)
     */
    public function activeTrackTypesForLernender(int $lernenderId): Collection
    {
        $today = now()->toDateString();

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
     * Prüft/normalisiert validierte Input-Daten für Save/Update:
     * - Typ-Logik (fach vs modul)
     * - Fach: fach_id muss im aktuellen Track(s) des Lernenden liegen
     * - Modul: modul_id muss zum Lehrberuf gehören (lehrberuf_module)
     * - Modul: offene Belegung finden/erstellen -> modul_belegung_id setzen
     * - semester_id wird aus pruefungsdatum ermittelt
     * - gewichtung_prozent Default = 100.00
     */
    public function normalizeForSave(array $data, int $lernenderId): array
    {
        $lernender = Lernender::query()
            ->select(['lernender_id', 'lehrbeginn', 'lehrende', 'lehrberuf_id'])
            ->where('lernender_id', $lernenderId)
            ->first();

        if (! $lernender) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => 'Lernender nicht gefunden.',
            ]);
        }

        $date = (string) $data['pruefungsdatum'];

        if ($date < (string) $lernender->lehrbeginn) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => 'Das Prüfungsdatum liegt vor dem Lehrbeginn.',
            ]);
        }

        if ($lernender->lehrende && $date > (string) $lernender->lehrende) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => 'Das Prüfungsdatum liegt nach dem Lehrende.',
            ]);
        }

        $semester = $this->semesterForDate($date);
        if (! $semester) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => 'Kein Semester gefunden, das dieses Datum abdeckt.',
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
                    'fach_id' => 'Bitte ein Fach wählen.',
                ]);
            }

            $fachId = (int) $data['fach_id'];
            $kategorieId = $this->kategorieFuer($lernenderId, 'fach', $fachId);

            return [
                'kategorie_id' => (int) $kategorieId,
                'semester_id' => (int) $semester->semester_id,
                'fach_id' => $fachId,
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
                    'modul_id' => 'Bitte ein Modul wählen.',
                ]);
            }

            $modulId = (int) $data['modul_id'];
            $kategorieId = $this->kategorieFuer($lernenderId, 'modul', $modulId);

            $modulBelegungId = $this->resolveOrCreateOpenModulBelegung($lernenderId, $modulId, $date);

            return [
                'kategorie_id' => (int) $kategorieId,
                'semester_id' => (int) $semester->semester_id,
                'fach_id' => null,
                'modul_belegung_id' => $modulBelegungId,
                'titel' => $data['titel'] ?? null,
                'pruefungsdatum' => $date,
                'note_wert' => $data['note_wert'],
                'gewichtung_prozent' => (float) $gewicht,
            ];
        }

        throw ValidationException::withMessages([
            'typ' => 'Ungültiger Typ.',
        ]);
    }

    /**
     * Einzige Zuordnungsregel für Noten und geplante Prüfungen: Fach muss für Beruf/Track freigegeben sein
     * (Kategorie folgt aus dem Fach), Modul muss zum Lehrberuf gehören (Kategorie = Lernort im Beruf).
     *
     * @param  'fach'|'modul'  $typ
     */
    public function kategorieFuer(int $lernenderId, string $typ, int $id): int
    {
        if ($typ === 'fach') {
            $kategorieId = $this->erlaubteFaecher($lernenderId)->whereKey($id)->value('kategorie_id');

            if (! $kategorieId) {
                throw ValidationException::withMessages(['fach_id' => 'Dieses Fach ist für den Lehrberuf oder Track nicht freigegeben.']);
            }

            return (int) $kategorieId;
        }

        $kategorieId = DB::table('lehrberuf_module')
            ->where('lehrberuf_id', (int) Lernender::query()->whereKey($lernenderId)->value('lehrberuf_id'))
            ->where('modul_id', $id)
            ->where('aktiv', 1)
            ->value('kategorie_id');

        if (! $kategorieId) {
            throw ValidationException::withMessages(['modul_id' => 'Dieses Modul gehört nicht zum Lehrberuf.']);
        }

        return (int) $kategorieId;
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
            return ['titel' => 'Neue Note', 'daten' => $daten];
        }
        if (preg_match('/^bearbeiten:(\d+)$/', $kontext, $m)) {
            $note = Note::query()
                ->with(['fach', 'modulBelegung.modul'])
                ->where('note_id', (int) $m[1])
                ->where('lernender_id', (int) $lernender->lernender_id)
                ->first();

            return $note ? ['titel' => 'Note bearbeiten', 'daten' => $daten + ['note' => $note]] : null;
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
        return Semester::query()
            ->where('start_datum', '<=', $date)
            ->where('end_datum', '>=', $date)
            ->first();
    }
}
