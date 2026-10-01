<x-app-layout>
    <x-slot name="title">{{ __('Lernende erfassen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.index')" :titel="__('Lernende erfassen')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('verwaltung.lernende._formular', ['lernender' => null])
        </div>
    </div>
</x-app-layout>
