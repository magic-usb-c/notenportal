<x-app-layout>
    <x-slot name="title">{{ __('Kalender') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Kalender')" schmal>
            @include('settings._tabs')
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
        <div class="flex np-spalte flex-col gap-8">

            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Kalender-Abo') }}</h2>
                <x-kalender-abo :token="$exportToken" :reset-route="route('settings.calendar.token.reset')" />
            </section>

            @if($lernender && $user->hasRole('Lernender'))
                @include('settings.partials.externe-kalender')
            @endif

        </div>
        </div>
    </div>
</x-app-layout>
