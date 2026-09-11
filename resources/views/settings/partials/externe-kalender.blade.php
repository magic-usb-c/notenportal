{{-- Schulnetz-Kalender einlesen: nur für Lernende (settings/calendar.blade.php prüft $lernender). --}}
@php
    $label = 'text-sm font-medium text-text';
    $feld = 'mt-1.5 w-full rounded-lg border border-border bg-input text-text px-3 h-10 focus:ring-2 focus:ring-ring focus:border-ring';
    $fehler = 'mt-1 text-xs text-red-600 dark:text-red-400';
@endphp
<div class="flex flex-col gap-4">
    <h3 class="font-semibold text-text text-sm">{{ __('Schulnetz-Kalender einlesen') }}</h3>
    <p class="text-xs text-muted">{{ __('iCal-Adresse aus dem Schulnetz hinterlegen: Prüfungen, Termine und Lektionen erscheinen dann in der Agenda.') }}</p>
    <form method="POST" action="{{ route('learner.calendar.feed.store') }}" class="flex flex-col gap-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <div>
            <label for="feed_label" class="{{ $label }}">{{ __('Bezeichnung') }}</label>
            <input id="feed_label" name="label" maxlength="80" value="{{ old('label', $feed?->label) }}" placeholder="{{ __('z. B. Schulnetz') }}" class="{{ $feld }}">
            @error('label')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="feed_url" class="{{ $label }}">{{ __('iCal-Adresse') }} <span class="text-red-600 dark:text-red-400">*</span></label>
            <input id="feed_url" name="url" type="text" required maxlength="2000"
                   value="{{ old('url', $feed?->url) }}" placeholder="https://…/kalender.ics" class="{{ $feld }}">
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

    @if($feed)
        <div class="rounded-lg border border-border bg-bg/40 px-3 py-2.5 text-xs text-muted flex flex-col gap-1">
            @if($feed->last_synced_at)
                <span>{{ __('Letzter Abgleich: :datum', ['datum' => $feed->last_synced_at->format('d.m.Y H:i')]) }}
                    · {{ $feed->last_status === \App\Models\CalendarFeed::OK ? __('erfolgreich') : __('fehlgeschlagen') }}</span>
                @if($feed->last_status === \App\Models\CalendarFeed::ERROR && $feed->last_error)
                    <span class="text-red-600 dark:text-red-400">{{ $feed->last_error }}</span>
                @endif
            @else
                <span>{{ __('Noch nicht abgeglichen.') }}</span>
            @endif
        </div>
        <form method="POST" action="{{ route('learner.calendar.sync') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <button :disabled="loading" class="w-full h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">{{ __('Jetzt abgleichen') }}</button>
        </form>
    @endif
</div>
