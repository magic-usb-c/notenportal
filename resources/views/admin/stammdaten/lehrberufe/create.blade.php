<x-app-layout>
    <x-slot name="title">{{ __('Neuer Lehrberuf') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.professions.index')" :titel="__('Neuer Lehrberuf')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.lehrberufe._formular', ['lehrberuf' => null])
        </div>
    </div>
</x-app-layout>
