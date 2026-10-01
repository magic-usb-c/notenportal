<x-guest-layout :titel="__('Eigenes Passwort festlegen')">
    <form method="POST" action="{{ route('password.initial.update') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        @include('auth._feld', ['name' => 'password', 'typ' => 'password', 'label' => __('Neues Passwort'), 'autocomplete' => 'new-password', 'autofocus' => true,
                                'hinweis' => __('Mindestens 10 Zeichen mit Buchstaben und Ziffern')])
        @include('auth._feld', ['name' => 'password_confirmation', 'typ' => 'password', 'label' => __('Passwort wiederholen'), 'autocomplete' => 'new-password'])
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross mt-1 w-full">{{ __('Speichern') }}</button>
    </form>

    <x-slot:fuss>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex min-h-6 items-center text-muted hover:text-text">{{ __('Abmelden') }}</button>
        </form>
    </x-slot:fuss>
</x-guest-layout>
