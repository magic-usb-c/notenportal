<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Lernender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $lernender = $user->lernender;

        $lehrberuf = $lernender
            ? DB::table('lehrberufe')->where('lehrberuf_id', $lernender->lehrberuf_id)->first(['name', 'kuerzel'])
            : null;

        return view('profile.edit', [
            'user' => $user,
            'lernender' => $lernender,
            'lehrberuf' => $lehrberuf,
            'bmsAktiv' => $lernender && $this->hatAktivenBmsTrack($lernender),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $daten = $request->validated();
        $lernender = $user->lernender;

        $user->fill(Arr::only($daten, $lernender ? ['email', 'darstellung'] : ['vorname', 'nachname', 'email', 'darstellung']));
        $user->save();

        if ($lernender) {
            $lernender->klasse_schule = $daten['klasse_schule'] ?? null;

            if ($this->hatAktivenBmsTrack($lernender)) {
                $lernender->klasse_bms = $daten['klasse_bms'] ?? null;
            }

            $lernender->save();
        }

        return redirect()->route('profile.edit')->with('success', 'Profil gespeichert.');
    }

    /** Darstellung aus dem Umschalter in der Navigation (ohne Neuladen). */
    public function darstellung(Request $request): JsonResponse
    {
        $validated = $request->validate(['darstellung' => ['required', 'in:system,hell,dunkel']]);
        $request->user()->update($validated);

        return response()->json(['darstellung' => $validated['darstellung']]);
    }

    private function hatAktivenBmsTrack(Lernender $lernender): bool
    {
        $heute = now()->toDateString();

        return DB::table('lernender_tracks')
            ->where('lernender_id', $lernender->lernender_id)
            ->where('track_typ', 'BMS')
            ->where('start_datum', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('end_datum')->orWhere('end_datum', '>=', $heute))
            ->exists();
    }
}
