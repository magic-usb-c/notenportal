<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Lernender;
use App\Models\User;
use App\Support\Darstellung;
use App\Support\DashboardKarten;
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
        $dashboardRolle = DashboardKarten::rolleFuer($user);

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
            'dashboardRolle' => $dashboardRolle,
            'dashboardKarten' => DashboardKarten::fuerRolle($dashboardRolle),
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
                'dichte' => $daten['dichte'] ?? Darstellung::DICHTE_NORMAL,
                'karten_ausgeblendet' => $this->kartenAusgeblendet($request, $user),
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

    /**
     * Schnellwechsel aus der Befehlspalette (resources/js/suche.js, window.npBefehl): ändert nur
     * den einen übergebenen Schlüssel, alle anderen Präferenzen bleiben unangetastet. Dashboard-
     * Karten werden hier nicht geändert (dafür: Profil-Formular).
     */
    public function preferences(Request $request): JsonResponse
    {
        abort_unless(Darstellung::praeferenzenOptionVerfuegbar(), 404);

        $regeln = [];
        if ($request->has('darstellung')) {
            $regeln['darstellung'] = ['in:system,hell,dunkel'];
        }
        if ($request->has('theme')) {
            $regeln['theme'] = ['nullable', Rule::in(array_keys(Theme::THEMES))];
        }
        if ($request->has('akzent')) {
            $regeln['akzent'] = ['nullable', Rule::in(array_keys(Darstellung::AKZENTE))];
        }
        if ($request->has('schrift')) {
            $regeln['schrift'] = [Rule::in(Darstellung::SCHRIFTGROESSEN)];
        }
        if ($request->has('dichte')) {
            $regeln['dichte'] = [Rule::in(Darstellung::DICHTEN)];
        }
        if ($request->has('bewegung')) {
            $regeln['bewegung'] = [Rule::in(Darstellung::BEWEGUNGEN)];
        }
        abort_if($regeln === [], 422);

        $validiert = $request->validate($regeln);
        $user = $request->user();

        if (array_key_exists('darstellung', $validiert)) {
            $user->darstellung = $validiert['darstellung'];
        }

        $praefSchluessel = array_intersect_key($validiert, array_flip(['theme', 'akzent', 'schrift', 'dichte', 'bewegung']));
        if ($praefSchluessel !== []) {
            $aktuell = Darstellung::fuer($user);
            $theme = array_key_exists('theme', $praefSchluessel) ? ($praefSchluessel['theme'] ?: null) : $aktuell['theme'];
            $akzent = array_key_exists('akzent', $praefSchluessel) ? ($praefSchluessel['akzent'] ?: null) : $aktuell['akzent'];

            $user->praeferenzen = [
                'theme' => $theme,
                'akzent' => $akzent,
                'schrift' => $praefSchluessel['schrift'] ?? $aktuell['schrift'],
                'bewegung' => $praefSchluessel['bewegung'] ?? $aktuell['bewegung'],
                'dichte' => $praefSchluessel['dichte'] ?? $aktuell['dichte'],
                'karten_ausgeblendet' => $aktuell['karten_ausgeblendet'],
            ];
            if (array_key_exists('theme', $praefSchluessel)) {
                $user->kontrast = $theme === Theme::KONTRAST;
            }
        }

        $user->save();

        return response()->json(['ok' => true]);
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

    /**
     * Ausgeblendete Dashboard-Karten aus dem Profilformular. Das Häkchenfeld erscheint nur, wenn
     * der Benutzer eine Dashboard-Rolle hat; ohne das versteckte Feld «karten_uebermittelt»
     * (z. B. alter Formularstand) bleibt die gespeicherte Auswahl unverändert – kein versehentliches
     * Ausblenden aller Karten, nur weil das Feld im Request fehlte.
     */
    private function kartenAusgeblendet(ProfileUpdateRequest $request, User $user): array
    {
        if (! $request->boolean('karten_uebermittelt')) {
            return Darstellung::fuer($user)['karten_ausgeblendet'];
        }

        $alle = array_keys(DashboardKarten::fuerRolle(DashboardKarten::rolleFuer($user)));
        $sichtbar = (array) $request->input('karten', []);

        return array_values(array_diff($alle, $sichtbar));
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
