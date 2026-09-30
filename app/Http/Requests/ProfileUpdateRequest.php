<?php

namespace App\Http\Requests;

use App\Support\Darstellung;
use App\Support\DashboardKarten;
use App\Support\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Eigenes Profil. Lernende ändern nur, was weder Berechtigungen noch Zuordnung
 * noch Notenlogik beeinflusst: E-Mail, Klassen, Darstellung. Namen stehen auf
 * der Druckansicht mit Unterschrift und bleiben beim Berufsbildner.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();

        $regeln = [
            'email' => ['required', 'email', 'max:255', Rule::unique('benutzer', 'email')->ignore($user->benutzer_id, 'benutzer_id')],
            'current_password' => [Rule::requiredIf(fn () => $this->input('email') !== $user->email), 'nullable', 'current_password'],
            'darstellung' => ['required', Rule::in(['system', 'hell', 'dunkel'])],
            'kontrast' => ['nullable', 'boolean'],
        ];

        if (Darstellung::praeferenzenOptionVerfuegbar()) {
            $rolle = DashboardKarten::rolleFuer($user);

            $regeln += [
                'theme' => ['nullable', Rule::in(array_keys(Theme::THEMES))],
                'akzent' => ['nullable', Rule::in([...array_keys(Darstellung::AKZENTE), Darstellung::AKZENT_EIGEN])],
                // Nur Pflicht, wenn tatsächlich «Eigene Farbe» gewählt ist; sonst bei Bedarf still auf
                // den Standard zurückgefallen (siehe Darstellung::fuer).
                'akzent_eigen' => [Rule::requiredIf($this->input('akzent') === Darstellung::AKZENT_EIGEN), 'nullable', 'regex:/^#[0-9a-f]{6}$/i'],
                'schrift' => ['nullable', Rule::in(Darstellung::SCHRIFTGROESSEN)],
                'schriftart' => ['nullable', Rule::in(Darstellung::SCHRIFTARTEN)],
                'bewegung_reduziert' => ['nullable', 'boolean'],
                'dichte' => ['nullable', Rule::in(Darstellung::DICHTEN)],
                'diagramm' => ['nullable', Rule::in(Darstellung::DIAGRAMME)],
                'notenanzeige' => ['nullable', Rule::in(Darstellung::NOTENANZEIGEN)],
                'ecken' => ['nullable', Rule::in(Darstellung::ECKEN)],
                'transparenz' => ['nullable', Rule::in(Darstellung::TRANSPARENZEN)],
                'tastenkuerzel' => ['nullable', Rule::in(Darstellung::TASTENKUERZEL)],
                'navigation' => ['nullable', Rule::in(Darstellung::NAVIGATIONEN)],
                'startseite' => ['nullable', Rule::in(array_keys(Darstellung::STARTSEITEN[$rolle] ?? ['dashboard' => 'dashboard']))],
                // «karten_uebermittelt» (verstecktes Feld im Formular) unterscheidet «Abschnitt nicht
                // angezeigt/gesendet» von «Benutzer hat alle Karten abgewählt» (min. eine Pflicht).
                'karten' => [Rule::requiredIf($this->boolean('karten_uebermittelt') && $rolle !== null), 'array'],
                'karten.*' => [Rule::in(array_keys(DashboardKarten::fuerRolle($rolle)))],
            ];
        }

        if ($user->lernender) {
            return $regeln + [
                'klasse_schule' => ['nullable', 'string', 'max:30'],
                'klasse_bms' => ['nullable', 'string', 'max:30'],
            ];
        }

        return $regeln + [
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['current_password' => __('aktuelles Passwort')];
    }
}
