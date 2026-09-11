<x-app-layout>
    <x-slot name="title">Note korrigieren</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Note korrigieren:
                <span class="text-muted">{{ $lernender->benutzer->nachname }} {{ $lernender->benutzer->vorname }}</span>
            </h2>
            <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">Zurück</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                @include('noten._formular', [
                    'zurueck' => route("{$bereich}.learners.grades.index", $lernender->lernender_id),
                    'vorschauUrl' => route("{$bereich}.learners.calculator.calculate", $lernender->lernender_id),
                    'action' => route("{$bereich}.learners.grades.update", [$lernender->lernender_id, $note->note_id]),
                    'note' => $note,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
