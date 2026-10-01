<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Schul-Tracks (BMS/ABU) eines Lernenden starten und beenden. */
class TrackController extends VerwaltungController
{
    public function store(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('verwalten', $lernender);

        $daten = $request->validate([
            'track_typ' => ['required', 'in:BMS,ABU'],
            'start_datum' => ['required', 'date'],
            'start_semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
        ]);

        $konflikt = $lernender->tracks()
            ->where('track_typ', $daten['track_typ'])
            ->where(fn ($q) => $q->whereNull('end_datum')->orWhereDate('start_datum', $daten['start_datum']))
            ->exists();

        if ($konflikt) {
            return back()->with('error', __('Es läuft bereits ein :typ-Track.', ['typ' => $daten['track_typ']]));
        }

        $lernender->tracks()->create([
            'track_typ' => $daten['track_typ'],
            'start_datum' => $daten['start_datum'],
            'start_semester_id' => (int) $daten['start_semester_id'],
        ]);

        return redirect()
            ->to($this->zuRoute($request, 'learners.show', [$lernender_id, 'tab' => 'profil']))
            ->with('success', __('Track gestartet.'));
    }

    public function beenden(Request $request, int $lernender_id, int $track_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('verwalten', $lernender);

        $track = $lernender->tracks()->whereKey($track_id)->whereNull('end_datum')->firstOrFail();

        $daten = $request->validate([
            'end_semester_id' => ['required', 'integer', 'exists:semester,semester_id'],
        ]);

        $reihenfolge = Semester::query()
            ->whereKey([$track->start_semester_id, (int) $daten['end_semester_id']])
            ->pluck('sortierung', 'semester_id');
        if ($reihenfolge[(int) $daten['end_semester_id']] < ($reihenfolge[$track->start_semester_id] ?? PHP_INT_MIN)) {
            return back()->with('error', __('Das Endsemester liegt vor dem Startsemester.'));
        }

        // DB-Constraint end_datum >= start_datum: ein künftiger Track endet an seinem Starttag.
        $track->update([
            'end_datum' => max(today(), $track->start_datum)->toDateString(),
            'end_semester_id' => (int) $daten['end_semester_id'],
        ]);

        return redirect()
            ->to($this->zuRoute($request, 'learners.show', [$lernender_id, 'tab' => 'profil']))
            ->with('success', __('Track beendet.'));
    }
}
