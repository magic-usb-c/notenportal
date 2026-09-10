{{-- «geändert von …», sobald jemand anderes als der Lernende die Note korrigiert hat --}}
@props(['note', 'lernenderBenutzerId'])

@php
    $geaendertVonId = (int) $note->aktualisiert_von_benutzer_id;
    $autor = $geaendertVonId && $geaendertVonId !== (int) $lernenderBenutzerId ? $note->aktualisiertVonBenutzer : null;
@endphp

@if($autor)
    <div {{ $attributes->merge(['class' => 'text-xs text-muted']) }}>
        geändert von {{ $autor->vorname }} {{ $autor->nachname }}, {{ $note->aktualisiert_am?->format('d.m.Y') }}
    </div>
@endif
