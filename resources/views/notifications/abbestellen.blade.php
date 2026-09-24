<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">{{ __(':label abbestellen?', ['label' => $label]) }}</h1>
    </div>

    @if($mandatory)
        <p class="text-sm text-muted text-center">
            {{ __('Der Betrieb schreibt diesen Anlass vor – er lässt sich nicht abbestellen.') }}
        </p>
        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="text-accent font-medium hover:underline">{{ __('Zur Anmeldung') }}</a>
        </p>
    @else
        <p class="text-sm text-muted text-center">{{ __('Du erhältst dann keine Mails mehr zu diesem Anlass.') }}</p>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6" x-data="{ loading: false }" @submit="loading = true">
            <button type="submit" :disabled="loading"
                    class="w-full flex justify-center items-center h-11 rounded-xl bg-accent text-accent-contrast font-semibold np-btn-primary disabled:opacity-60">
                {{ __('Abbestellen') }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('login') }}" class="text-muted hover:text-text">{{ __('Doch nicht – zur Anmeldung') }}</a>
        </p>
    @endif
</x-guest-layout>
