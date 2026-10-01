<x-app-layout>
    <x-slot name="title">{{ __('Notenkategorie bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.categories.index')" :titel="__('Notenkategorie bearbeiten')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.kategorien._formular')
        </div>
    </div>
</x-app-layout>
