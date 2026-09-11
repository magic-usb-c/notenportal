{{-- Notenformular im Drawer (per Fetch geladen oder nach einem Validierungsfehler). Erwartet $bezugOptionen, $semesterListe, $drawer; optional $note --}}
@if(empty($bezugOptionen))
    <p class="mb-5 text-sm text-note-knapp">{{ __('Für dich sind noch keine Fächer oder Module freigegeben.') }}</p>
@endif
@include('noten._formular', [
    'action' => ($note ?? null) ? route('learner.grades.update', $note->note_id) : route('learner.grades.store'),
    'zurueck' => route('learner.grades.index'),
    'vorschauUrl' => route('learner.grades.calculator.calculate'),
])
