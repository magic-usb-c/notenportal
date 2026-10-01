<x-app-layout>
    <x-slot name="title">{{ __('Neues Semester') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.semesters.index')" :titel="__('Neues Semester')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.semester._formular', ['semester' => null])
        </div>
    </div>
</x-app-layout>
