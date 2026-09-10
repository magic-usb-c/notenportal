<x-app-layout>
    <x-slot name="title">Note bearbeiten</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Note bearbeiten</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass rounded-3xl p-6 sm:p-8">
                @include('noten._formular', [
                    'action' => route('lernender.noten.update', $note->note_id),
                    'zurueck' => route('lernender.noten.index', ['semester_id' => $note->semester_id]),
                    'vorschauUrl' => route('lernender.noten.rechner.berechnen'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
