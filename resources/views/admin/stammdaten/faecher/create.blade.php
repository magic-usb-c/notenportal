<x-app-layout>
    <x-slot name="title">{{ __('Neues Fach') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.subjects.index')" :titel="__('Neues Fach')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.faecher._formular', ['fach' => null])
        </div>
    </div>
</x-app-layout>
