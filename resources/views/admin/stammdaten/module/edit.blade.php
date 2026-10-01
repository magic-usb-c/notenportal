<x-app-layout>
    <x-slot name="title">{{ __('Modul bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.modules.index')" :titel="__('Modul bearbeiten')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.module._formular')
        </div>
    </div>
</x-app-layout>
