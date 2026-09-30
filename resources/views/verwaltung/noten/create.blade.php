<x-app-layout>
    <x-slot name="title">{{ __('Note erfassen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.grades.index', $lernender->lernender_id)" :titel="__('Note erfassen')" :untertitel="$lernender->benutzer->nachname.' '.$lernender->benutzer->vorname" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
            <div class="np-karte p-6 space-y-5">
                @if(empty($bezugOptionen))
                    <p class="text-sm text-note-knapp">{{ __('Keine Fächer oder Module verfügbar – zuerst einen Track oder Lehrberuf-Module einrichten.') }}</p>
                @endif

                @include('noten._formular', [
                    'zurueck' => route("{$bereich}.learners.grades.index", $lernender->lernender_id),
                    'vorschauUrl' => route("{$bereich}.learners.calculator.calculate", $lernender->lernender_id),
                    'action' => route("{$bereich}.learners.grades.store", $lernender->lernender_id),
                ])
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
