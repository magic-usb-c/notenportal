<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\PasswortOrakelSperre;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Eigenes Konto. Lernende ändern nur, was weder Berechtigungen noch Zuordnung
 * noch Notenlogik beeinflusst: E-Mail und Klassen. Namen stehen auf der
 * Druckansicht mit Unterschrift und bleiben beim Berufsbildner.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();

        $regeln = [
            'email' => ['required', 'email', 'max:255', Rule::unique('benutzer', 'email')->ignore($user->benutzer_id, 'benutzer_id')],
            'current_password' => [Rule::requiredIf(fn () => $this->input('email') !== $user->email), 'nullable', 'current_password'],
            // Nur im Ersatzformular ohne persönliche Darstellung; sonst speichert ProfileController::preferences()
            'darstellung' => ['sometimes', 'required', Rule::in(['system', 'hell', 'dunkel'])],
            'kontrast' => ['nullable', 'boolean'],
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

    /** Das aktuelle Passwort wird nur geprüft, wenn es mitgeschickt wird (E-Mail-Änderung): nur dann bremsen. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('current_password') || $this->input('email') !== $this->user()->email) {
            PasswortOrakelSperre::pruefen($this, 'current_password');
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->filled('current_password') && $validator->errors()->has('current_password')) {
            PasswortOrakelSperre::fehlversuch($this);
        }

        parent::failedValidation($validator);
    }

    protected function passedValidation(): void
    {
        if ($this->filled('current_password')) {
            PasswortOrakelSperre::zuruecksetzen($this);
        }
    }
}
