<x-guest-layout :titel="__('Passwort bestätigen')" :text="__('Das ist ein geschützter Bereich. Bitte bestätige dein Passwort, bevor du fortfährst.')">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @include('auth._feld', ['name' => 'password', 'typ' => 'password', 'label' => __('Passwort'), 'autocomplete' => 'current-password', 'autofocus' => true])
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross mt-1 w-full">{{ __('Bestätigen') }}</button>
    </form>
</x-guest-layout>
