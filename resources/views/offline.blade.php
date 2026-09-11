<x-guest-layout>
    <div class="text-center space-y-4">
        <h1 class="text-2xl font-semibold text-text">{{ __('Keine Verbindung') }}</h1>
        <p class="text-sm text-muted">{{ __('Diese Seite ist offline nicht verfügbar. Bitte die Verbindung prüfen und es erneut versuchen.') }}</p>
        <button type="button" onclick="location.reload()"
                class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
            {{ __('Erneut versuchen') }}
        </button>
    </div>
</x-guest-layout>
