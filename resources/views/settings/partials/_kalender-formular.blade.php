{{-- Formular für einen Kalender (neu oder bearbeiten). $feed ist null bei einem neuen Kalender.
     feed_id wird IMMER mitgeschickt – sonst legt feedStore() bei jedem Speichern einen neuen Kalender an. --}}
@php
    $label = 'text-sm font-medium text-text';
    $feld = 'mt-1.5 w-full rounded-lg border border-border bg-input text-text px-3 h-10 focus:ring-2 focus:ring-ring focus:border-ring';
    $fehler = 'mt-1 text-xs text-red-600 dark:text-red-400';
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
            @unless($feed)<span class="text-red-600 dark:text-red-400">*</span>@endunless
        </label>
        {{-- Die bestehende Adresse ist ein Geheimnis und wird nie in dieses Feld eingesetzt. --}}
        <input id="feed_url_{{ $idSuffix }}" name="url" type="text" @unless($feed) required @endunless maxlength="2000"
               value="{{ old('url') }}"
               placeholder="{{ $feed ? __('unverändert lassen oder neue Adresse eintragen') : 'https://…/kalender.ics' }}" class="{{ $feld }}">
        @error('url')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>
    <fieldset class="flex flex-col gap-2">
        <legend class="{{ $label }}">{{ __('Übernehmen') }}</legend>
        <label class="inline-flex items-center gap-2 text-sm text-text">
            <input type="checkbox" name="import_exams" value="1" @checked(old('import_exams', $feed?->import_exams ?? true)) class="rounded border-border-strong text-accent focus:ring-ring">
            {{ __('Prüfungen') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-text">
            <input type="checkbox" name="import_appointments" value="1" @checked(old('import_appointments', $feed?->import_appointments ?? true)) class="rounded border-border-strong text-accent focus:ring-ring">
            {{ __('Termine') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-text">
            <input type="checkbox" name="import_lessons" value="1" @checked(old('import_lessons', $feed?->import_lessons ?? true)) class="rounded border-border-strong text-accent focus:ring-ring">
            {{ __('Lektionen (Stundenplan)') }}
        </label>
    </fieldset>
    <button :disabled="loading" class="h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary disabled:opacity-60">{{ __('Speichern') }}</button>
</form>
