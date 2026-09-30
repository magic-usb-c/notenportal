<x-app-layout>
    <x-slot name="title">{{ __('Neue Note') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Neue Note') }}" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6 sm:p-8">
                @if(empty($bezugOptionen))
                    <p class="mb-5 text-sm text-note-knapp">{{ __('Für dich sind noch keine Fächer oder Module freigegeben.') }}</p>
                @endif

                @include('noten._formular', [
                    'action' => route('learner.grades.store'),
                    'zurueck' => $pruefung ? route('learner.exams.index') : route('learner.grades.index'),
                    'vorschauUrl' => route('learner.grades.calculator.calculate'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
