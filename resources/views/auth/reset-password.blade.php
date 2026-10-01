<x-guest-layout :titel="__('Neues Passwort festlegen')">
    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('auth._feld', ['name' => 'email', 'typ' => 'email', 'label' => __('E-Mail'), 'autocomplete' => 'username', 'wert' => old('email', $email)])
        @include('auth._feld', ['name' => 'password', 'typ' => 'password', 'label' => __('Neues Passwort'), 'autocomplete' => 'new-password', 'autofocus' => true,
                                'hinweis' => __('Mindestens 10 Zeichen mit Buchstaben und Ziffern')])
        @include('auth._feld', ['name' => 'password_confirmation', 'typ' => 'password', 'label' => __('Passwort wiederholen'), 'autocomplete' => 'new-password'])
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross mt-1 w-full">{{ __('Passwort speichern') }}</button>
    </form>

    <x-slot:fuss>
        <a href="{{ route('login') }}" class="inline-flex min-h-6 items-center text-accent-text hover:underline underline-offset-2">{{ __('Zurück zur Anmeldung') }}</a>
    </x-slot:fuss>
</x-guest-layout>
