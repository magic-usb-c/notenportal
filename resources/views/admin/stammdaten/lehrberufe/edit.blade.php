<x-app-layout>
    <x-slot name="title">{{ __('Lehrberuf bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.professions.index')" :titel="__('Lehrberuf bearbeiten')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.show', $lehrberuf->lehrberuf_id) }}" class="np-knopf np-knopf-sekundaer">{{ __('Module/Fächer') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.lehrberufe._formular')
        </div>
    </div>
</x-app-layout>
