<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Http\Requests\PraeferenzenRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Lernender;
use App\Models\User;
use App\Support\Darstellung;
use App\Support\DashboardKarten;
use App\Support\Farbe;
use App\Support\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Konto (Name, E-Mail, Klassen). Die persönliche Darstellung speichert jede Änderung einzeln über
     * preferences(); nur ohne Spalte «praeferenzen» stehen Hell/Dunkel und Kontrast hier im Formular.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $daten = $request->validated();
        $lernender = $user->lernender;

        $user->fill(Arr::only($daten, $lernender ? ['email', 'darstellung'] : ['vorname', 'nachname', 'email', 'darstellung']));

        if (! Darstellung::praeferenzenOptionVerfuegbar() && Theme::kontrastOptionVerfuegbar()) {
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

        return redirect()->route('settings.profile')->with('success', __('Profil gespeichert.'));
    }

    /**
     * «Auf Standard zurücksetzen» (Profil «Darstellung»): alle Felder dieses Formularabschnitts
     * auf den Standard, ausser die ausgeblendeten Dashboard-Karten (eigener Abschnitt) und die
     * Spalte «darstellung» (hell/dunkel-Umschalter, unabhängig von den Präferenzen).
     */
    public function resetPreferences(Request $request): RedirectResponse
    {
        abort_unless(Darstellung::praeferenzenOptionVerfuegbar(), 404);

        $user = $request->user();
        $aktuell = Darstellung::fuer($user);

        $user->praeferenzen = [
            'theme' => null,
            'akzent' => null,
            'akzent_eigen' => null,
            'schrift' => Darstellung::SCHRIFT_NORMAL,
            'schriftart' => Darstellung::SCHRIFTART_STANDARD,
            'bewegung' => Darstellung::BEWEGUNG_NORMAL,
            'dichte' => Darstellung::DICHTE_NORMAL,
            'diagramm' => Darstellung::DIAGRAMM_STANDARD,
            'notenanzeige' => Darstellung::NOTENANZEIGE_1,
            'ecken' => Darstellung::ECKEN_RUND,
            'transparenz' => Darstellung::TRANSPARENZ_NORMAL,
            'tastenkuerzel' => Darstellung::TASTENKUERZEL_AN,
            'navigation' => Darstellung::NAVIGATION_SEITE,
            'startseite' => 'dashboard',
            'karten_ausgeblendet' => $aktuell['karten_ausgeblendet'],
        ];
        $user->kontrast = false;
        $user->save();

        return redirect()->route('settings.profile')->with('success', __('Darstellung auf Standard zurückgesetzt.'));
    }

    /** Darstellung aus dem Umschalter in der Navigation (ohne Neuladen). */
    public function darstellung(Request $request): JsonResponse
    {
        $validated = $request->validate(['darstellung' => ['required', 'in:system,hell,dunkel']]);
        $request->user()->update($validated);

        return response()->json(['darstellung' => $validated['darstellung']]);
    }

    /**
     * Einzelne Darstellungs-Einstellungen ohne Neuladen: aus den Einstellungen, wo jede Änderung sofort
     * gilt (wie in den Systemeinstellungen), und aus der Befehlspalette (resources/js/suche.js). Geändert
     * werden nur die übergebenen Schlüssel, alle anderen bleiben unangetastet. Bei einer eigenen
     * Akzentfarbe kommen die serverseitig auf Kontrast geprüften Tokens zurück (App\Support\Farbe).
     */
    public function preferences(PraeferenzenRequest $request): JsonResponse
    {
        abort_unless(Darstellung::praeferenzenOptionVerfuegbar(), 404);

        $validiert = $request->validated();
        abort_if($validiert === [], 422);

        // Gesperrt gelesen und geschrieben: zwei Änderungen kurz nacheinander (zweiter Tab, Befehlspalette)
        // überschreiben sich sonst gegenseitig, weil jede das ganze JSON zurückschreibt.
        $user = DB::transaction(function () use ($request, $validiert): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());

            if (array_key_exists('darstellung', $validiert)) {
                $user->darstellung = $validiert['darstellung'];
            }

            $praef = Arr::except($validiert, ['darstellung', 'akzent_eigen', 'karten']);
            if ($praef !== [] || array_key_exists('akzent_eigen', $validiert) || array_key_exists('karten', $validiert)) {
                $aktuell = Darstellung::fuer($user);
                $theme = array_key_exists('theme', $praef) ? ($praef['theme'] ?: null) : $aktuell['theme'];
                $akzent = array_key_exists('akzent', $praef) ? ($praef['akzent'] ?: null) : $aktuell['akzent'];
                $akzentEigen = $validiert['akzent_eigen'] ?? $aktuell['akzent_eigen'];

                $neu = array_merge($aktuell, $praef, [
                    'theme' => $theme,
                    'akzent' => $akzent,
                    // Die eigene Farbe gilt nur, solange «Eigene Farbe» gewählt ist (wie Darstellung::fuer)
                    'akzent_eigen' => $akzent === Darstellung::AKZENT_EIGEN && $akzentEigen !== null ? strtolower($akzentEigen) : null,
                ]);
                if (array_key_exists('karten', $validiert)) {
                    $alle = array_keys(DashboardKarten::fuerRolle(DashboardKarten::rolleFuer($user)));
                    $neu['karten_ausgeblendet'] = array_values(array_diff($alle, $validiert['karten']));
                }
                $user->praeferenzen = $neu;

                if (array_key_exists('theme', $praef)) {
                    $user->kontrast = $theme === Theme::KONTRAST;
                }
            }

            $user->save();

            return $user;
        });

        $antwort = ['ok' => true];
        if (array_key_exists('akzent', $validiert) || array_key_exists('akzent_eigen', $validiert)) {
            $antwort['akzent_stil'] = Farbe::styleBlock($user->praeferenzen['akzent_eigen'] ?? null);
        }

        return response()->json($antwort);
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

    /** Duplikat aus SettingsController::hatAktivenBmsTrack() (bewusst, siehe docs/audit-backlog.md). */
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
