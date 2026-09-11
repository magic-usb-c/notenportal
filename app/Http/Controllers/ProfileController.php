<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Lernender;
use App\Support\Darstellung;
use App\Support\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            'kontrastOption' => Theme::kontrastOptionVerfuegbar(),
            'praeferenzenOption' => Darstellung::praeferenzenOptionVerfuegbar(),
            'betriebTheme' => Theme::betrieb(),
            'praeferenzen' => Darstellung::fuer($user),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $daten = $request->validated();
        $lernender = $user->lernender;

        $user->fill(Arr::only($daten, $lernender ? ['email', 'darstellung'] : ['vorname', 'nachname', 'email', 'darstellung']));

        if (Darstellung::praeferenzenOptionVerfuegbar()) {
            $theme = $daten['theme'] ?? null ?: null;
            $user->praeferenzen = [
                'theme' => $theme,
                'akzent' => ($daten['akzent'] ?? null) ?: null,
                'schrift' => $daten['schrift'] ?? Darstellung::SCHRIFT_NORMAL,
                'bewegung' => $request->boolean('bewegung_reduziert') ? Darstellung::BEWEGUNG_REDUZIERT : Darstellung::BEWEGUNG_NORMAL,
            ];
            // Alt-Logik (Theme::fuer ohne Präferenzen) bleibt konsistent: Kontrast auch hier gesetzt.
            $user->kontrast = $theme === Theme::KONTRAST;
        } elseif (Theme::kontrastOptionVerfuegbar()) {
            $user->kontrast = $request->boolean('kontrast');
        }

        $user->save();

        if ($lernender) {
            $lernender->klasse_schule = $daten['klasse_schule'] ?? null;

            if ($this->hatAktivenBmsTrack($lernender)) {
                $lernender->klasse_bms = $daten['klasse_bms'] ?? null;
            }

            $lernender->save();
        }

        return redirect()->route('profile.edit')->with('success', __('Profil gespeichert.'));
    }

    /** Darstellung aus dem Umschalter in der Navigation (ohne Neuladen). */
    public function darstellung(Request $request): JsonResponse
    {
        $validated = $request->validate(['darstellung' => ['required', 'in:system,hell,dunkel']]);
        $request->user()->update($validated);

        return response()->json(['darstellung' => $validated['darstellung']]);
    }

    /** Sprache aus Benutzermenü oder Profil; Gäste nur für die Session. Nur bei eingeschalteter Sprachwahl. */
    public function locale(Request $request): RedirectResponse
    {
        abort_unless(SetLocale::wahlAktiv(), 404);

        $locale = $request->validate(['locale' => ['required', Rule::in(SetLocale::SPRACHEN)]])['locale'];
        $request->user()?->update(['locale' => $locale]);
        $request->session()->put(SetLocale::SESSION, $locale);

        return back()->with('success', __('Sprache gespeichert.', [], $locale));
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
