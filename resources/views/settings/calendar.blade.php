<x-app-layout>
    <x-slot name="title">{{ __('Kalender') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Kalender')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">

            @include('settings._tabs')

            <div class="mt-5 space-y-5">

                <div class="np-karte p-6">
                    <h3 class="font-semibold text-text text-sm mb-3">{{ __('Kalender-Abo') }}</h3>
                    <x-kalender-abo :token="$exportToken" :reset-route="route('settings.calendar.token.reset')" />
                </div>

                @if($lernender && $user->hasRole('Lernender'))
                    <div class="np-karte p-6">
                        @include('settings.partials.externe-kalender')
                    </div>
                @endif

            </div>
        </div>
        </div>
    </div>
</x-app-layout>
