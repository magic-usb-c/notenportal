<?php

namespace App\Services\Noten;

use App\Models\Fach;
use App\Models\Kategorie;
use App\Models\ModulBelegung;
use App\Models\ModulNoteGruppe;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * app/Services/Noten/NoteService.php
 *
 * Zweck:
 * - Zentrale Logik rund um Noten (Queries, Filter, Form-Optionen, Validierung)
 * - Damit Lernender/Berufsbildner/Admin später dieselben Bausteine nutzen
 *   und wir keine Logik 3x kopieren müssen.
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
     * (Nur das anzeigen, was dem Lernenden gehört.)
     */
    public function formOptionsForLernender(int $lernenderId): array
    {
        $kategorien = Kategorie::query()->orderBy('sortierung')->get();
        $faecher     = Fach::query()->where('aktiv', 1)->orderBy('track_typ')->orderBy('name')->get();

        $modulBelegungen = ModulBelegung::query()
            ->with('modul')
            ->where('lernender_id', $lernenderId)
            ->orderByDesc('start_datum')
            ->get();

        $gruppen = ModulNoteGruppe::query()
            ->whereIn('modul_belegung_id', $modulBelegungen->pluck('modul_belegung_id'))
            ->orderBy('bezeichnung')
            ->get();

        return compact('kategorien', 'faecher', 'modulBelegungen', 'gruppen');
    }

    /**
     * Prüft/normalisiert validierte Input-Daten für Save/Update:
     * - Typ-Logik (fach vs modul)
     * - Ownership-Checks (Modul-Belegung gehört dem Lernenden)
     * - Gruppe passt zur Belegung
     * - Semester wird aus Datum ermittelt
     *
     * Erwartet: $data ist bereits durch $request->validate(...) gelaufen.
     */
    public function normalizeForSave(array $data, int $lernenderId): array
    {
        // Typ-Logik: genau eins von fach_id / modul_belegung_id
        if (($data['typ'] ?? null) === 'fach') {
            if (empty($data['fach_id']) || !empty($data['modul_belegung_id'])) {
                throw ValidationException::withMessages([
                    'fach_id' => 'Bitte ein Fach wählen (und kein Modul).',
                ]);
            }
            $data['modul_belegung_id'] = null;
            $data['gruppe_id'] = null;
        } elseif (($data['typ'] ?? null) === 'modul') {
            if (empty($data['modul_belegung_id']) || !empty($data['fach_id'])) {
                throw ValidationException::withMessages([
                    'modul_belegung_id' => 'Bitte eine Modul-Belegung wählen (und kein Fach).',
                ]);
            }
            $data['fach_id'] = null;
        } else {
            throw ValidationException::withMessages([
                'typ' => 'Ungültiger Typ.',
            ]);
        }

        // Modul-Belegung muss dem Lernenden gehören
        if (!empty($data['modul_belegung_id'])) {
            $ok = ModulBelegung::query()
                ->where('modul_belegung_id', (int) $data['modul_belegung_id'])
                ->where('lernender_id', $lernenderId)
                ->exists();

            if (!$ok) {
                throw ValidationException::withMessages([
                    'modul_belegung_id' => 'Diese Modul-Belegung gehört nicht zu dir.',
                ]);
            }
        }

        // Gruppe muss zur gleichen Belegung gehören
        if (!empty($data['gruppe_id'])) {
            $ok = ModulNoteGruppe::query()
                ->where('gruppe_id', (int) $data['gruppe_id'])
                ->where('modul_belegung_id', (int) $data['modul_belegung_id'])
                ->exists();

            if (!$ok) {
                throw ValidationException::withMessages([
                    'gruppe_id' => 'Diese Gruppe passt nicht zur gewählten Modul-Belegung.',
                ]);
            }
        }

        // Semester automatisch aus Datum bestimmen
        $semester = $this->semesterForDate((string) $data['pruefungsdatum']);
        if (!$semester) {
            throw ValidationException::withMessages([
                'pruefungsdatum' => 'Kein Semester gefunden, das dieses Datum abdeckt.',
            ]);
        }

        // Normalisierte Rückgabe (nur Spalten, die wir wirklich speichern wollen)
        return [
            'kategorie_id' => (int) $data['kategorie_id'],
            'semester_id' => (int) $semester->semester_id,
            'fach_id' => !empty($data['fach_id']) ? (int) $data['fach_id'] : null,
            'modul_belegung_id' => !empty($data['modul_belegung_id']) ? (int) $data['modul_belegung_id'] : null,
            'gruppe_id' => !empty($data['gruppe_id']) ? (int) $data['gruppe_id'] : null,
            'titel' => $data['titel'] ?? null,
            'pruefungsdatum' => (string) $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'] ?? null,
        ];
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
     * - gewichtet = (sum(note_wert * gewichtung) / sum(gewichtung)) nur wenn Gewichtungen vorhanden
     */
    public function calcAverages(Collection $notes): array
    {
        $count = $notes->count();
        if ($count === 0) {
            return [null, null, 0, 0];
        }

        $avgUnweighted = round((float) $notes->avg('note_wert'), 2);

        $weightedNotes = $notes->whereNotNull('gewichtung_prozent');
        $missingWeights = $count - $weightedNotes->count();

        $wSum = (float) $weightedNotes->sum('gewichtung_prozent');
        if ($wSum > 0) {
            $weighted = (float) $weightedNotes->sum(function ($n) {
                return (float) $n->note_wert * (float) $n->gewichtung_prozent;
            });
            $avgWeighted = round($weighted / $wSum, 2);
        } else {
            $avgWeighted = null;
        }

        return [$avgUnweighted, $avgWeighted, $missingWeights, $count];
    }
}
