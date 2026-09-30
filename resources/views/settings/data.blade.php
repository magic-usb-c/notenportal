<x-app-layout>
    <x-slot name="title">{{ __('Daten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Daten')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">

            @include('settings._tabs')

            <div class="mt-5 space-y-5">

                <div class="np-karte p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-text text-sm">{{ __('Datenauskunft') }}</h3>
                            <p class="mt-1 text-sm text-muted">{{ __('Diese Datei enthält alle Daten, die das Notenportal zu diesem Konto gespeichert hat.') }}</p>
                        </div>
                        <a href="{{ route('profile.data-export') }}"
                           class="np-knopf np-knopf-sekundaer self-start sm:self-auto shrink-0">
                            {{ __('Daten herunterladen') }}
                        </a>
                    </div>
                </div>

            </div>
        </div>
        </div>
    </div>
</x-app-layout>
