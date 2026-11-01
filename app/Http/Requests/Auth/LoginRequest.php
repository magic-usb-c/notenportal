<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Credentials für Login gegen deine Tabelle `benutzer`.
     * - aktiv muss 1 sein
     * - geloescht_am muss NULL sein
     * password wird über User::getAuthPassword() aus passwort_hash geprüft
     */
    public function credentials(): array
    {
        return [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'aktiv' => 1,
            'geloescht_am' => null,
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // remember=false: weil deine Tabelle keinen remember_token hat
        if (! Auth::attempt($this->credentials(), false)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return $this->normalisierteEmail().'|'.$this->ip();
    }

    /**
     * Reduziert die E-Mail auf das, was die Datenbank als gleich vergleicht (Gross-/Kleinschreibung,
     * Kombinationszeichen, unsichtbare Zeichen), damit alle Schreibweisen denselben Zähler treffen.
     */
    private function normalisierteEmail(): string
    {
        $email = $this->string('email')->toString();

        if (class_exists(\Normalizer::class)) {
            $email = \Normalizer::normalize($email, \Normalizer::FORM_KD) ?: $email;
        }

        $email = preg_replace('/\p{M}+/u', '', $email) ?? $email;
        $email = Str::lower(Str::transliterate(Str::lower($email)));

        return preg_replace('/[^a-z0-9@._+-]/', '', $email) ?? '';
    }
}
