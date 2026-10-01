{{-- Abgabetermin erfassen/bearbeiten (Rückmeldung #14) – nur möglich, wenn ein einzelner Lernender gefiltert ist. --}}
@php
    $label = 'text-sm font-medium text-text';
    $feld = 'np-feld mt-1.5';
    $fehler = 'mt-1 text-xs text-note-ungenuegend';
    $b = $bearbeiten;
@endphp
<form method="POST" action="{{ $b ? route($bereich.'.exams.update', $b->pruefung_id) : route($bereich.'.exams.store') }}"
      class="flex flex-col gap-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($b)
        @method('PUT')
    @else
        <input type="hidden" name="_drawer" value="abgabetermin">
    @endif
    <input type="hidden" name="lernender_id" value="{{ $filter['lernender_id'] }}">

    <div>
        <label for="abgabe-bezug" class="{{ $label }}">{{ __('Fach / Modul') }}</label>
        <select id="abgabe-bezug" name="bezug" required class="{{ $feld }}" @error('bezug') aria-invalid="true" aria-describedby="abgabe-bezug-fehler" @enderror>
            <option value="">{{ __('Bitte wählen…') }}</option>
            @foreach($bezugOptionen as $gruppe => $optionen)
                <optgroup label="{{ $gruppe }}">
                    @foreach($optionen as $o)
                        <option value="{{ $o['wert'] }}" @selected(old('bezug', $b?->bezug()) === $o['wert'])>{{ $o['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('bezug')<p id="abgabe-bezug-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="abgabe-titel" class="{{ $label }}">{{ __('Titel') }}</label>
        <input id="abgabe-titel" name="titel" required maxlength="150" value="{{ old('titel', $b?->titel) }}"
               class="{{ $feld }}" @error('titel') aria-invalid="true" aria-describedby="abgabe-titel-fehler" @enderror>
        @error('titel')<p id="abgabe-titel-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="abgabe-datum" class="{{ $label }}">{{ __('Datum') }}</label>
            <input type="date" id="abgabe-datum" name="datum" required value="{{ old('datum', $b?->datum?->toDateString()) }}"
                   class="{{ $feld }} tabular-nums" @error('datum') aria-invalid="true" aria-describedby="abgabe-datum-fehler" @enderror>
            @error('datum')<p id="abgabe-datum-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="abgabe-gewichtung" class="{{ $label }}">{{ __('Gewichtung') }}</label>
            <x-gewicht-feld id="abgabe-gewichtung" :wert="old('gewichtung_prozent', $b?->gewichtung_prozent)" placeholder="100"
                            :aria-invalid="$errors->has('gewichtung_prozent') ? 'true' : null"
                            :aria-describedby="$errors->has('gewichtung_prozent') ? 'abgabe-gewichtung-fehler' : null" />
            @error('gewichtung_prozent')<p id="abgabe-gewichtung-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <x-formular-aktionen schliessen="abgabetermin">{{ $b ? __('Speichern') : __('Erfassen') }}</x-formular-aktionen>
</form>
