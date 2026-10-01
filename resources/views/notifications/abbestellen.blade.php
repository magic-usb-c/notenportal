<x-guest-layout :titel="__(':label abbestellen?', ['label' => $label])">
    @if($mandatory)
        <p class="text-center text-sm text-muted">{{ __('Der Betrieb schreibt diesen Anlass vor – er lässt sich nicht abbestellen.') }}</p>
    @else
        <p class="text-center text-sm text-muted">{{ __('Du erhältst dann keine Mails mehr zu diesem Anlass.') }}</p>
        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6" x-data="{ loading: false }" @submit="loading = true">
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross w-full">{{ __('Abbestellen') }}</button>
        </form>
    @endif

    <x-slot:fuss>
        <a href="{{ route('login') }}" class="inline-flex min-h-6 items-center text-accent-text hover:underline underline-offset-2">{{ $mandatory ? __('Zur Anmeldung') : __('Doch nicht – zur Anmeldung') }}</a>
    </x-slot:fuss>
</x-guest-layout>
