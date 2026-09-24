{{-- Abgabetermin erfassen/bearbeiten (Rückmeldung #14) – nur möglich, wenn ein einzelner Lernender gefiltert ist. --}}
@php
    $label = 'text-sm font-medium text-text';
    $feld = 'mt-1.5 w-full rounded-lg border border-border bg-input text-text px-3 h-10 focus:ring-2 focus:ring-ring focus:border-ring';
    $fehler = 'mt-1 text-xs text-note-ungenuegend';
    $b = $bearbeiten;
@endphp
<form method="POST" action="{{ $b ? route($bereich.'.exams.update', $b->pruefung_id) : route($bereich.'.exams.store') }}"
      class="flex flex-col gap-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($b)
        @method('PUT')
    @endif
    <input type="hidden" name="lernender_id" value="{{ $filter['lernender_id'] }}">

    <div>
        <label for="abgabe-bezug" class="{{ $label }}">{{ __('Fach / Modul') }} <span class="text-note-ungenuegend">*</span></label>
        <select id="abgabe-bezug" name="bezug" required class="{{ $feld }}">
            <option value="">{{ __('Bitte wählen') }}</option>
            @foreach($bezugOptionen as $gruppe => $optionen)
                <optgroup label="{{ $gruppe }}">
                    @foreach($optionen as $o)
                        <option value="{{ $o['wert'] }}" @selected(old('bezug', $b?->bezug()) === $o['wert'])>{{ $o['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('bezug')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="abgabe-titel" class="{{ $label }}">{{ __('Titel') }} <span class="text-note-ungenuegend">*</span></label>
        <input id="abgabe-titel" name="titel" required maxlength="150" value="{{ old('titel', $b?->titel) }}" class="{{ $feld }}">
        @error('titel')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="abgabe-datum" class="{{ $label }}">{{ __('Datum') }} <span class="text-note-ungenuegend">*</span></label>
            <input type="date" id="abgabe-datum" name="datum" required value="{{ old('datum', $b?->datum?->toDateString()) }}" class="{{ $feld }}">
            @error('datum')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="abgabe-gewichtung" class="{{ $label }}">{{ __('Gewichtung %') }}</label>
            <input type="number" id="abgabe-gewichtung" name="gewichtung_prozent" min="0" max="100" step="0.01"
                   value="{{ old('gewichtung_prozent', $b?->gewichtung_prozent) }}" class="{{ $feld }}">
            @error('gewichtung_prozent')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex gap-2 mt-2">
        <a href="{{ route($bereich.'.exams.index', $filter) }}" class="inline-flex items-center justify-center px-4 h-11 rounded-xl glass-btn text-text text-sm">{{ __('Abbrechen') }}</a>
        <button :disabled="loading" class="flex-1 h-11 rounded-xl bg-accent text-accent-contrast font-semibold np-btn-primary disabled:opacity-60">{{ $b ? __('Speichern') : __('Erfassen') }}</button>
    </div>
</form>
