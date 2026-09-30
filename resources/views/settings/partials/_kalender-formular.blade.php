{{-- Formular für einen Kalender (neu oder bearbeiten). $feed ist null bei einem neuen Kalender.
     feed_id wird IMMER mitgeschickt – sonst legt feedStore() bei jedem Speichern einen neuen Kalender an. --}}
@php
    $label = 'text-sm font-medium text-text';
    $feld = 'np-feld mt-1.5';
    $fehler = 'mt-1 text-xs text-note-ungenuegend';
    $idSuffix = $feed?->id ?? 'neu';
@endphp
<form method="POST" action="{{ route('learner.calendar.feed.store') }}" class="flex flex-col gap-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    <input type="hidden" name="feed_id" value="{{ $feed?->id }}">
    <div>
        <label for="feed_label_{{ $idSuffix }}" class="{{ $label }}">{{ __('Bezeichnung') }}</label>
        <input id="feed_label_{{ $idSuffix }}" name="label" maxlength="80" value="{{ old('label', $feed?->label) }}" placeholder="{{ __('z. B. Schulnetz') }}" class="{{ $feld }}">
        @error('label')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="feed_url_{{ $idSuffix }}" class="{{ $label }}">
            {{ __('iCal-Adresse') }}
            @unless($feed)<span class="text-note-ungenuegend">*</span>@endunless
        </label>
        {{-- Die bestehende Adresse ist ein Geheimnis und wird nie in dieses Feld eingesetzt. --}}
        <input id="feed_url_{{ $idSuffix }}" name="url" type="text" @unless($feed) required @endunless maxlength="2000"
               value="{{ old('url') }}"
               placeholder="{{ $feed ? __('unverändert lassen oder neue Adresse eintragen') : 'https://…/kalender.ics' }}" class="{{ $feld }}">
        @error('url')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>
    <fieldset class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <legend class="{{ $label }} mb-2">{{ __('Übernehmen') }}</legend>
        <label class="inline-flex items-center gap-2 text-sm text-text">
            <input type="checkbox" role="switch" name="import_exams" value="1" @checked(old('import_exams', $feed?->import_exams ?? true)) class="np-schalter">
            {{ __('Prüfungen') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-text">
            <input type="checkbox" role="switch" name="import_appointments" value="1" @checked(old('import_appointments', $feed?->import_appointments ?? true)) class="np-schalter">
            {{ __('Termine') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-text">
            <input type="checkbox" role="switch" name="import_lessons" value="1" @checked(old('import_lessons', $feed?->import_lessons ?? true)) class="np-schalter">
            {{ __('Lektionen (Stundenplan)') }}
        </label>
    </fieldset>
    <div class="flex justify-end">
        <button :disabled="loading" class="np-knopf np-knopf-primaer min-w-24">{{ __('Speichern') }}</button>
    </div>
</form>
