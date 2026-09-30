{{-- Externe Kalender einlesen: nur für Lernende (settings/calendar.blade.php prüft $lernender). Bis zu 5 Kalender. --}}
<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Externe Kalender') }}</h2>
    <div class="np-karte np-gruppe">
        @foreach($feeds as $f)
            @php
                // Bei einem Validierungsfehler auf genau diesem Feed muss die Bearbeitungsansicht offen bleiben,
                // sonst verschwindet die Fehlermeldung hinter dem eingeklappten Alpine-Panel.
                $offenWegenFehler = $errors->any() && (int) old('feed_id') === $f->id;
                $fehlgeschlagen = $f->last_status === \App\Models\CalendarFeed::ERROR;
            @endphp
            <div class="px-4 py-3" x-data="{ bearbeiten: {{ $offenWegenFehler ? 'true' : 'false' }} }">
                <div class="flex items-center gap-3">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-fill text-muted" aria-hidden="true"><x-symbol name="calendar" class="size-4" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-text">{{ $f->label ?: $f->host() }}</div>
                        <div class="truncate text-xs text-muted">
                            {{ $f->host() }} ·
                            @if($f->last_synced_at)
                                {{ __('Letzter Abgleich: :datum', ['datum' => $f->last_synced_at->format('d.m.Y H:i')]) }}
                                · <span @class(['text-note-ungenuegend' => $fehlgeschlagen])>{{ $fehlgeschlagen ? __('fehlgeschlagen') : __('erfolgreich') }}</span>
                            @else
                                {{ __('Noch nicht abgeglichen.') }}
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <form method="POST" action="{{ route('learner.calendar.feed.sync', $f->id) }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                            @csrf
                            <button :disabled="loading" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Jetzt abgleichen') }}</button>
                        </form>
                        <button type="button" @click="bearbeiten = !bearbeiten" :aria-expanded="bearbeiten" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Bearbeiten') }}</button>
                        <form method="POST" action="{{ route('learner.calendar.feed.destroy', $f->id) }}" data-bestaetigen="{{ __('Kalender entfernen?') }}" data-bestaetigen-knopf="{{ __('Entfernen') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                            @csrf
                            @method('DELETE')
                            <button :disabled="loading" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr" aria-label="{{ __('Entfernen') }}" title="{{ __('Entfernen') }}"><x-symbol name="trash" /></button>
                        </form>
                    </div>
                </div>
                @if($fehlgeschlagen && $f->last_error)
                    <p class="mt-2 pl-11 text-xs text-note-ungenuegend">{{ $f->last_error }}</p>
                @endif
                <div class="mt-2 flex flex-wrap gap-1.5 pl-11">
                    @foreach([['import_exams', __('Prüfungen')], ['import_appointments', __('Termine')], ['import_lessons', __('Lektionen (Stundenplan)')]] as [$feld, $bezeichnung])
                        <span class="np-marke {{ $f->$feld ? 'bg-accent/12 text-accent-text' : 'text-muted line-through' }}">{{ $bezeichnung }}</span>
                    @endforeach
                </div>
                <div x-show="bearbeiten" x-cloak class="mt-3 ml-11 border-t border-border pt-3">
                    @include('settings.partials._kalender-formular', ['feed' => $f])
                </div>
            </div>
        @endforeach

        @if($feeds->count() < \App\Models\CalendarFeed::MAX_PRO_LERNENDEM)
            <div class="px-4 py-3" x-data="{ bearbeiten: {{ $feeds->isEmpty() || (old('feed_id') !== null && ! filled(old('feed_id'))) ? 'true' : 'false' }} }">
                @if($feeds->isNotEmpty())
                    <button type="button" @click="bearbeiten = !bearbeiten" :aria-expanded="bearbeiten" class="np-knopf np-knopf-schlicht -ml-2">
                        <x-symbol name="plus" strich="2" />{{ __('Kalender hinzufügen') }}
                    </button>
                @else
                    <h3 class="text-sm font-medium text-text">{{ __('Kalender hinzufügen') }}</h3>
                @endif
                <div x-show="bearbeiten" x-cloak class="mt-3">
                    @include('settings.partials._kalender-formular', ['feed' => null])
                </div>
            </div>
        @else
            <p class="px-4 py-3 text-xs text-muted">{{ __('Höchstens :max Kalender. Bitte zuerst einen entfernen.', ['max' => \App\Models\CalendarFeed::MAX_PRO_LERNENDEM]) }}</p>
        @endif
    </div>
    <p class="mt-2 px-1 text-xs text-muted">{{ __('iCal-Adresse hinterlegen: Prüfungen, Termine und Lektionen erscheinen dann in der Agenda.') }}</p>
    <div class="mt-3">
        @include('settings.partials.kalender-anleitungen')
    </div>
</section>
