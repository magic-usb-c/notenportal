<x-app-layout>
    <x-slot name="title">{{ __('Note korrigieren') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.grades.index', $lernender->lernender_id)" :titel="__('Note korrigieren')" :untertitel="$lernender->benutzer->nachname.' '.$lernender->benutzer->vorname" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="np-karte np-spalte p-6">
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
