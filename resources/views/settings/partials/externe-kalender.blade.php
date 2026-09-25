{{-- Externe Kalender einlesen: nur für Lernende (settings/calendar.blade.php prüft $lernender). Bis zu 5 Kalender. --}}
<div class="flex flex-col gap-4">
    <div>
        <h3 class="font-semibold text-text text-sm">{{ __('Externe Kalender') }}</h3>
        <p class="text-xs text-muted mt-1">{{ __('iCal-Adresse hinterlegen: Prüfungen, Termine und Lektionen erscheinen dann in der Agenda.') }}</p>
    </div>

    @include('settings.partials.kalender-anleitungen')

    @if($feeds->isNotEmpty())
        <ul class="flex flex-col gap-2.5">
            @foreach($feeds as $f)
                @php
                    // Bei einem Validierungsfehler auf genau diesem Feed muss die Bearbeitungsansicht offen bleiben,
                    // sonst verschwindet die Fehlermeldung hinter dem eingeklappten Alpine-Panel.
                    $offenWegenFehler = $errors->any() && (int) old('feed_id') === $f->id;
                @endphp
                <li class="rounded-lg border border-border bg-bg/40 px-3 py-2.5" x-data="{ bearbeiten: {{ $offenWegenFehler ? 'true' : 'false' }} }">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-text truncate">{{ $f->label ?: $f->host() }}</div>
                            <div class="text-xs text-muted truncate">{{ $f->host() }}</div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button" @click="bearbeiten = !bearbeiten" :aria-expanded="bearbeiten" class="inline-flex items-center px-2.5 h-8 rounded-lg text-xs glass-btn text-text">{{ __('Bearbeiten') }}</button>
                            <form method="POST" action="{{ route('learner.calendar.feed.sync', $f->id) }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                @csrf
                                <button :disabled="loading" class="inline-flex items-center px-2.5 h-8 rounded-lg text-xs glass-btn text-text disabled:opacity-60">{{ __('Jetzt abgleichen') }}</button>
                            </form>
                            <form method="POST" action="{{ route('learner.calendar.feed.destroy', $f->id) }}" onsubmit="return confirm('{{ __('Kalender entfernen?') }}');" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                @csrf
                                @method('DELETE')
                                <button :disabled="loading" class="inline-flex items-center justify-center w-8 h-8 rounded-lg disabled:opacity-60 text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10" aria-label="{{ __('Entfernen') }}">×</button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                        @if($f->last_synced_at)
                            <span>{{ __('Letzter Abgleich: :datum', ['datum' => $f->last_synced_at->format('d.m.Y H:i')]) }}
                                · {{ $f->last_status === \App\Models\CalendarFeed::OK ? __('erfolgreich') : __('fehlgeschlagen') }}</span>
                        @else
                            <span>{{ __('Noch nicht abgeglichen.') }}</span>
                        @endif
                        @if($f->last_status === \App\Models\CalendarFeed::ERROR && $f->last_error)
                            <span class="text-note-ungenuegend">{{ $f->last_error }}</span>
                        @endif
                    </div>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach([['import_exams', __('Prüfungen')], ['import_appointments', __('Termine')], ['import_lessons', __('Lektionen (Stundenplan)')]] as [$feld, $bezeichnung])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] {{ $f->$feld ? 'bg-accent/10 text-accent-text' : 'text-muted line-through' }}">{{ $bezeichnung }}</span>
                        @endforeach
                    </div>

                    <div x-show="bearbeiten" x-cloak class="mt-3 pt-3 border-t border-border">
                        @include('settings.partials._kalender-formular', ['feed' => $f])
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if($feeds->count() < \App\Models\CalendarFeed::MAX_PRO_LERNENDEM)
        <div class="rounded-lg border border-border bg-bg/40 px-3 py-3" x-data="{ bearbeiten: {{ $feeds->isEmpty() || (old('feed_id') !== null && ! filled(old('feed_id'))) ? 'true' : 'false' }} }">
            @if($feeds->isNotEmpty())
                <button type="button" @click="bearbeiten = !bearbeiten" :aria-expanded="bearbeiten" class="inline-flex items-center px-2.5 h-8 rounded-lg text-xs glass-btn text-text">{{ __('Kalender hinzufügen') }}</button>
            @else
                <h4 class="text-sm font-medium text-text mb-2">{{ __('Kalender hinzufügen') }}</h4>
            @endif
            <div x-show="bearbeiten" x-cloak class="{{ $feeds->isNotEmpty() ? 'mt-3 pt-3 border-t border-border' : '' }}">
                @include('settings.partials._kalender-formular', ['feed' => null])
            </div>
        </div>
    @else
        <p class="text-xs text-muted">{{ __('Höchstens :max Kalender. Bitte zuerst einen entfernen.', ['max' => \App\Models\CalendarFeed::MAX_PRO_LERNENDEM]) }}</p>
    @endif
</div>
