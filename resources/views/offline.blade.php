<x-guest-layout :titel="__('Keine Verbindung')">
    <p class="text-center text-sm text-muted">{{ __('Diese Seite ist offline nicht verfügbar. Bitte die Verbindung prüfen und es erneut versuchen.') }}</p>
    {{-- Inline statt Alpine: offline ist nicht sicher, dass die Skripte im Cache liegen. --}}
    <button type="button" onclick="location.reload()" class="np-knopf np-knopf-primaer np-knopf-gross mt-6 w-full">{{ __('Erneut versuchen') }}</button>
</x-guest-layout>
