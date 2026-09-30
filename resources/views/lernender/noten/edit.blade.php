<x-app-layout>
    <x-slot name="title">{{ __('Note bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Note bearbeiten') }}" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6 sm:p-8">
                @include('noten._formular', [
                    'action' => route('learner.grades.update', $note->note_id),
                    'zurueck' => route('learner.grades.index', ['semester_id' => $note->semester_id]),
                    'vorschauUrl' => route('learner.grades.calculator.calculate'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
