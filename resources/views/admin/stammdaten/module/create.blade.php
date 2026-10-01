<x-app-layout>
    <x-slot name="title">{{ __('Neues Modul') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.modules.index')" :titel="__('Neues Modul')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.module._formular', ['modul' => null])
        </div>
    </div>
</x-app-layout>
