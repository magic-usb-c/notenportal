<x-guest-layout :titel="__('Passwort vergessen')" :text="__('Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link zum Zurücksetzen.')">
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @include('auth._feld', ['name' => 'email', 'typ' => 'email', 'label' => __('E-Mail'), 'autocomplete' => 'username', 'wert' => old('email'), 'autofocus' => true])
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross mt-1 w-full">{{ __('Link zusenden') }}</button>
    </form>

    <x-slot:fuss>
        <a href="{{ route('login') }}" class="inline-flex min-h-6 items-center text-accent-text hover:underline underline-offset-2">{{ __('Zurück zur Anmeldung') }}</a>
    </x-slot:fuss>
</x-guest-layout>
