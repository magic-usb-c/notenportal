<x-app-layout>
    <x-slot name="title">Note korrigieren</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Note korrigieren" :untertitel="$lernender->benutzer->nachname.' '.$lernender->benutzer->vorname" schmal>
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">Zurück</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
            <div class="rounded-xl border border-border bg-card p-6">
                @include('noten._formular', [
                    'zurueck' => route("{$bereich}.learners.grades.index", $lernender->lernender_id),
                    'vorschauUrl' => route("{$bereich}.learners.calculator.calculate", $lernender->lernender_id),
                    'action' => route("{$bereich}.learners.grades.update", [$lernender->lernender_id, $note->note_id]),
                    'note' => $note,
                ])
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
