<?php

namespace App\Http\Requests;

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
        ];

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
        return ['current_password' => 'aktuelles Passwort'];
    }
}
