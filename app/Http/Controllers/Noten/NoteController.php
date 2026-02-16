<?php

namespace App\Http\Controllers\Noten;

use App\Http\Controllers\Controller;
use App\Models\Kategorie;
use App\Models\Fach;
use App\Models\ModulBelegung;
use App\Models\ModulNoteGruppe;
use App\Models\Note;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $lernender = $user->lernender;

        if (!$lernender) {
            abort(403);
        }

        $q = Note::query()
            ->with(['kategorie', 'semester', 'fach', 'modulBelegung.modul', 'gruppe'])
            ->where('lernender_id', $lernender->lernender_id)
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id');

        if ($request->filled('kategorie_id')) {
            $q->where('kategorie_id', (int) $request->input('kategorie_id'));
        }

        if ($request->filled('semester_id')) {
            $q->where('semester_id', (int) $request->input('semester_id'));
        }

        $notes = (clone $q)->paginate(25)->withQueryString();

        $allForAvg = (clone $q)->get(['note_wert', 'gewichtung_prozent', 'kategorie_id']);

        [$avgUnweighted, $avgWeighted, $missingWeights, $count] = $this->calcAvg($allForAvg);

        $kategorien = Kategorie::query()->orderBy('sortierung')->get();
        $semester = Semester::query()->orderBy('sortierung')->get();

        return view('noten.index', compact(
            'notes',
            'kategorien',
            'semester',
            'avgUnweighted',
            'avgWeighted',
            'missingWeights',
            'count'
        ));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $lernender = $user->lernender;

        if (!$lernender) {
            abort(403);
        }

        $kategorien = Kategorie::query()->orderBy('sortierung')->get();
        $faecher = Fach::query()->where('aktiv', 1)->orderBy('track_typ')->orderBy('name')->get();

        $modulBelegungen = ModulBelegung::query()
            ->with('modul')
            ->where('lernender_id', $lernender->lernender_id)
            ->orderByDesc('start_datum')
            ->get();

        $gruppen = ModulNoteGruppe::query()
            ->whereIn('modul_belegung_id', $modulBelegungen->pluck('modul_belegung_id'))
            ->orderBy('bezeichnung')
            ->get();

        return view('noten.create', compact('kategorien', 'faecher', 'modulBelegungen', 'gruppen'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $lernender = $user->lernender;

        if (!$lernender) {
            abort(403);
        }

        $data = $request->validate([
            'kategorie_id' => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_belegung_id' => ['nullable', 'integer', 'exists:modul_belegungen,modul_belegung_id'],
            'gruppe_id' => ['nullable', 'integer', 'exists:modul_note_gruppen,gruppe_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // XOR / Typ-Logik
        if ($data['typ'] === 'fach') {
            if (empty($data['fach_id']) || !empty($data['modul_belegung_id'])) {
                throw ValidationException::withMessages(['fach_id' => 'Bitte ein Fach wählen (und kein Modul).']);
            }
            $data['modul_belegung_id'] = null;
            $data['gruppe_id'] = null;
        } else {
            if (empty($data['modul_belegung_id']) || !empty($data['fach_id'])) {
                throw ValidationException::withMessages(['modul_belegung_id' => 'Bitte eine Modul-Belegung wählen (und kein Fach).']);
            }
        }

        // Modul-Belegung muss dem Lernenden gehören
        if (!empty($data['modul_belegung_id'])) {
            $ok = ModulBelegung::query()
                ->where('modul_belegung_id', $data['modul_belegung_id'])
                ->where('lernender_id', $lernender->lernender_id)
                ->exists();

            if (!$ok) {
                throw ValidationException::withMessages(['modul_belegung_id' => 'Diese Modul-Belegung gehört nicht zu dir.']);
            }
        }

        // Gruppe muss zur gleichen Belegung gehören
        if (!empty($data['gruppe_id'])) {
            $ok = ModulNoteGruppe::query()
                ->where('gruppe_id', $data['gruppe_id'])
                ->where('modul_belegung_id', $data['modul_belegung_id'])
                ->exists();

            if (!$ok) {
                throw ValidationException::withMessages(['gruppe_id' => 'Diese Gruppe passt nicht zur gewählten Modul-Belegung.']);
            }
        }

        // Semester automatisch aus Datum bestimmen
        $sem = Semester::query()
            ->where('start_datum', '<=', $data['pruefungsdatum'])
            ->where('end_datum', '>=', $data['pruefungsdatum'])
            ->first();

        if (!$sem) {
            throw ValidationException::withMessages(['pruefungsdatum' => 'Kein Semester gefunden, das dieses Datum abdeckt.']);
        }

        Note::create([
            'lernender_id' => $lernender->lernender_id,
            'kategorie_id' => (int) $data['kategorie_id'],
            'semester_id' => $sem->semester_id,
            'fach_id' => $data['fach_id'] ?? null,
            'modul_belegung_id' => $data['modul_belegung_id'] ?? null,
            'gruppe_id' => $data['gruppe_id'] ?? null,
            'titel' => $data['titel'] ?? null,
            'pruefungsdatum' => $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'] ?? null,
            'erfasst_von_benutzer_id' => $user->benutzer_id,
            'aktualisiert_von_benutzer_id' => null,
        ]);

        return redirect()->route('noten.index')->with('status', 'Note gespeichert.');
    }

    public function edit(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = Note::query()
            ->with(['fach', 'modulBelegung.modul', 'gruppe'])
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender->lernender_id)
            ->firstOrFail();

        $kategorien = Kategorie::query()->orderBy('sortierung')->get();
        $faecher = Fach::query()->where('aktiv', 1)->orderBy('track_typ')->orderBy('name')->get();

        $modulBelegungen = ModulBelegung::query()
            ->with('modul')
            ->where('lernender_id', $lernender->lernender_id)
            ->orderByDesc('start_datum')
            ->get();

        $gruppen = ModulNoteGruppe::query()
            ->whereIn('modul_belegung_id', $modulBelegungen->pluck('modul_belegung_id'))
            ->orderBy('bezeichnung')
            ->get();

        return view('noten.edit', compact('note', 'kategorien', 'faecher', 'modulBelegungen', 'gruppen'));
    }

    public function update(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender->lernender_id)
            ->firstOrFail();

        $data = $request->validate([
            'kategorie_id' => ['required', 'integer', 'exists:kategorien,kategorie_id'],
            'typ' => ['required', 'in:fach,modul'],
            'fach_id' => ['nullable', 'integer', 'exists:faecher,fach_id'],
            'modul_belegung_id' => ['nullable', 'integer', 'exists:modul_belegungen,modul_belegung_id'],
            'gruppe_id' => ['nullable', 'integer', 'exists:modul_note_gruppen,gruppe_id'],
            'titel' => ['nullable', 'string', 'max:150'],
            'pruefungsdatum' => ['required', 'date'],
            'note_wert' => ['required', 'numeric', 'min:1', 'max:6'],
            'gewichtung_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // XOR / Typ-Logik
        if ($data['typ'] === 'fach') {
            if (empty($data['fach_id']) || !empty($data['modul_belegung_id'])) {
                throw ValidationException::withMessages(['fach_id' => 'Bitte ein Fach wählen (und kein Modul).']);
            }
            $data['modul_belegung_id'] = null;
            $data['gruppe_id'] = null;
        } else {
            if (empty($data['modul_belegung_id']) || !empty($data['fach_id'])) {
                throw ValidationException::withMessages(['modul_belegung_id' => 'Bitte eine Modul-Belegung wählen (und kein Fach).']);
            }
        }

        // Modul-Belegung muss dem Lernenden gehören
        if (!empty($data['modul_belegung_id'])) {
            $ok = ModulBelegung::query()
                ->where('modul_belegung_id', $data['modul_belegung_id'])
                ->where('lernender_id', $lernender->lernender_id)
                ->exists();

            if (!$ok) {
                throw ValidationException::withMessages(['modul_belegung_id' => 'Diese Modul-Belegung gehört nicht zu dir.']);
            }
        }

        // Gruppe muss zur gleichen Belegung gehören
        if (!empty($data['gruppe_id'])) {
            $ok = ModulNoteGruppe::query()
                ->where('gruppe_id', $data['gruppe_id'])
                ->where('modul_belegung_id', $data['modul_belegung_id'])
                ->exists();

            if (!$ok) {
                throw ValidationException::withMessages(['gruppe_id' => 'Diese Gruppe passt nicht zur gewählten Modul-Belegung.']);
            }
        }

        // Semester automatisch aus Datum bestimmen
        $sem = Semester::query()
            ->where('start_datum', '<=', $data['pruefungsdatum'])
            ->where('end_datum', '>=', $data['pruefungsdatum'])
            ->first();

        if (!$sem) {
            throw ValidationException::withMessages(['pruefungsdatum' => 'Kein Semester gefunden, das dieses Datum abdeckt.']);
        }

        $note->update([
            'kategorie_id' => (int) $data['kategorie_id'],
            'semester_id' => $sem->semester_id,
            'fach_id' => $data['fach_id'] ?? null,
            'modul_belegung_id' => $data['modul_belegung_id'] ?? null,
            'gruppe_id' => $data['gruppe_id'] ?? null,
            'titel' => $data['titel'] ?? null,
            'pruefungsdatum' => $data['pruefungsdatum'],
            'note_wert' => $data['note_wert'],
            'gewichtung_prozent' => $data['gewichtung_prozent'] ?? null,
            'aktualisiert_von_benutzer_id' => $user->benutzer_id,
        ]);

        return redirect()->route('noten.index')->with('status', 'Note aktualisiert.');
    }

    public function destroy(Request $request, int $note_id)
    {
        $user = $request->user();
        $lernender = $user->lernender;

        if (!$lernender) {
            abort(403);
        }

        $note = Note::query()
            ->where('note_id', $note_id)
            ->where('lernender_id', $lernender->lernender_id)
            ->firstOrFail();

        $note->delete();

        return redirect()->route('noten.index')->with('status', 'Note gelöscht.');
    }

    private function calcAvg($notes): array
    {
        $count = $notes->count();
        if ($count === 0) {
            return [null, null, 0, 0];
        }

        $avgUnweighted = round($notes->avg('note_wert'), 2);

        $weightedNotes = $notes->whereNotNull('gewichtung_prozent');
        $missingWeights = $count - $weightedNotes->count();

        $wSum = (float) $weightedNotes->sum('gewichtung_prozent');
        if ($wSum > 0) {
            $weighted = $weightedNotes->sum(function ($n) {
                return (float) $n->note_wert * (float) $n->gewichtung_prozent;
            });
            $avgWeighted = round($weighted / $wSum, 2);
        } else {
            $avgWeighted = null;
        }

        return [$avgUnweighted, $avgWeighted, $missingWeights, $count];
    }
}
