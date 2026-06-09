<?php

namespace App\Services\Noten;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\Lernender;
use App\Models\ModulBelegung;
use App\Models\ModulNoteGruppe;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
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
            ->with(['kategorie', 'semester', 'fach', 'modulBelegung.modul', 'gruppe'])
            ->where('lernender_id', $lernenderId)
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id');
    }

    /**
     * Standard-Filter für Index-Seite.
     */
    public function applyIndexFilters(Builder $q, ?int $kategorieId, ?int $semesterId): Builder
    {
        if (!empty($kategorieId)) {
            $q->where('kategorie_id', $kategorieId);
        }

        if (!empty($semesterId)) {
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
        $kategorien = Kategorie::query()->orderBy('sortierung')->get();

        // ✅ Track-abhängige Fächer (nur aktive Tracks des Lernenden)
        $trackTyps = $this->activeTrackTypesForLernender($lernenderId);

        $faecherQuery = Fach::query()
            ->where('aktiv', 1)
            ->orderBy('name');

        // Wenn wir aktive Tracks kennen: nur diese anzeigen.
        // Falls noch keine Tracks erfasst sind, lieber leer statt "alles".
        if ($trackTyps->isNotEmpty()) {
            $faecherQuery->whereIn('track_typ', $trackTyps->all());
        } else {
            $faecherQuery->whereRaw('1=0');
        }

        $faecher = $faecherQuery->get();

        // Modul-Liste nach Lehrberuf (mit "has_open_belegung" Flag & Sortierung)
        $module = $this->modulesForLernender($lernenderId);

        // Semester-Liste begrenzen (ab Lehrbeginn)
        $semester = $this->semestersForLernender($lernenderId);

        return compact('kategorien', 'faecher', 'module', 'semester');
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

        if (!$l) {
            return collect();
        }

        $from = (string) $l->lehrbeginn;
        $to   = $l->lehrende ? (string) $l->lehrende : now()->toDateString();

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

        if (!$lernender) {
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

        if (!$lernender) {
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
        if (!$semester) {
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

            // Sicherheitscheck: Fach muss im aktiven Track des Lernenden sein
            $trackTyps = $this->activeTrackTypesForLernender($lernenderId);
            if ($trackTyps->isEmpty()) {
                throw ValidationException::withMessages([
                    'fach_id' => 'Für dich ist aktuell kein Track hinterlegt.',
                ]);
            }

            $allowed = Fach::query()
                ->where('fach_id', $fachId)
                ->where('aktiv', 1)
                ->whereIn('track_typ', $trackTyps->all())
                ->exists();

            if (!$allowed) {
                throw ValidationException::withMessages([
                    'fach_id' => 'Dieses Fach gehört nicht zu deinem aktuellen Track.',
                ]);
            }

            return [
                'kategorie_id' => (int) $data['kategorie_id'],
                'semester_id' => (int) $semester->semester_id,
                'fach_id' => $fachId,
                'modul_belegung_id' => null,
                'gruppe_id' => null,
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

            $allowed = DB::table('lehrberuf_module')
                ->where('lehrberuf_id', (int) $lernender->lehrberuf_id)
                ->where('modul_id', $modulId)
                ->where('aktiv', 1)
                ->exists();

            if (!$allowed) {
                throw ValidationException::withMessages([
                    'modul_id' => 'Dieses Modul gehört nicht zu deinem Lehrberuf.',
                ]);
            }

            $modulBelegungId = $this->resolveOrCreateOpenModulBelegung($lernenderId, $modulId, $date);

            return [
                'kategorie_id' => (int) $data['kategorie_id'],
                'semester_id' => (int) $semester->semester_id,
                'fach_id' => null,
                'modul_belegung_id' => $modulBelegungId,
                'gruppe_id' => null,
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

            // Optional: Gruppe automatisch (falls du das mittlerweile drin hast/aktivieren willst)
            // -> hier nur, wenn du das wirklich willst und die Models fillable korrekt sind.
            // ModulNoteGruppe::create([...]);

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

    /**
     * Durchschnitt berechnen:
     * - ungewichtet = simple avg(note_wert)
     * - gewichtet   = sum(note_wert * gewichtung) / sum(gewichtung)
     * - missingWeights = Anzahl Noten ohne explizite Gewichtung (null/leer → 100% als Fallback)
     *
     * Rückgabe: [avgUnweighted, avgWeighted, missingWeights, count]
     */
    public function calcAverages(Collection $notes): array
    {
        $count = $notes->count();
        if ($count === 0) {
            return [null, null, 0, 0];
        }

        $avgUnweighted = round((float) $notes->avg('note_wert'), 2);

        $wSum          = 0.0;
        $weightedSum   = 0.0;
        $missingWeights = 0;

        foreach ($notes as $n) {
            $w = $n->gewichtung_prozent;
            if ($w === null || $w === '') {
                $w = 100.0;
                $missingWeights++;
            }
            $w = (float) $w;

            $wSum        += $w;
            $weightedSum += (float) $n->note_wert * $w;
        }

        $avgWeighted = $wSum > 0 ? round($weightedSum / $wSum, 2) : null;

        return [$avgUnweighted, $avgWeighted, $missingWeights, $count];
    }
}
