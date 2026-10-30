{{-- Notenformular im Drawer (per Fetch geladen oder nach einem Validierungsfehler). Erwartet $bezugOptionen, $semesterListe, $drawer; optional $note --}}
@if(empty($bezugOptionen) && ! ($note ?? null))
    <x-leer symbol="clipboard-document-check" :titel="__('Keine Fächer oder Module')">
        <a href="{{ route('modules.index') }}" class="np-knopf np-knopf-sekundaer">{{ __('Module ansehen') }}</a>
    </x-leer>
@else
    @include('noten._formular', [
        'action' => ($note ?? null) ? route('learner.grades.update', $note->note_id) : route('learner.grades.store'),
        'zurueck' => route('learner.grades.index'),
        'vorschauUrl' => route('learner.grades.calculator.calculate'),
    ])
@endif
