<x-guest-layout>
    <div class="text-center space-y-4">
        <h1 class="text-2xl font-semibold text-text">{{ __('Keine Verbindung') }}</h1>
        <p class="text-sm text-muted">{{ __('Diese Seite ist offline nicht verfügbar. Bitte die Verbindung prüfen und es erneut versuchen.') }}</p>
        <button type="button" onclick="location.reload()"
                class="np-knopf np-knopf-primaer">
            {{ __('Erneut versuchen') }}
        </button>
    </div>
</x-guest-layout>
