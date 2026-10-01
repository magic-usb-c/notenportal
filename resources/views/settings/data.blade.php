<x-app-layout>
    <x-slot name="title">{{ __('Daten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Daten')" schmal />
        {{-- Tabs auf eigener Zeile: ortsfest, unabhängig von der Breite des wechselnden Titels --}}
        <div class="np-spalte mt-4">@include('settings._tabs')</div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
        <div class="flex np-spalte flex-col gap-8">

            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Datenauskunft') }}</h2>
                <div class="np-karte">
                    <x-einstellung :label="__('Alle Daten dieses Kontos')" :hinweis="__('Diese Datei enthält alle Daten, die das Notenportal zu diesem Konto gespeichert hat.')">
                        <a href="{{ route('profile.data-export') }}" class="np-knopf np-knopf-sekundaer">
                            <x-symbol name="arrow-down-tray" strich="2" />{{ __('Daten herunterladen') }}
                        </a>
                    </x-einstellung>
                </div>
            </section>

        </div>
        </div>
    </div>
</x-app-layout>
