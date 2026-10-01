<x-app-layout>
    <x-slot name="title">{{ __('Lernende bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.show', $lernender->lernender_id)" :titel="__('Lernende bearbeiten')"
                      :untertitel="$lernender->benutzer->nachname.' '.$lernender->benutzer->vorname" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('verwaltung.lernende._formular')
        </div>
    </div>
</x-app-layout>
