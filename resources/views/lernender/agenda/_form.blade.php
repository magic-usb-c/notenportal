@php
    $label = 'text-sm font-medium text-text';
    $feld = 'mt-1.5 w-full rounded-lg border border-border bg-input text-text px-3 h-10 focus:ring-2 focus:ring-ring focus:border-ring';
    $textarea = 'mt-1.5 w-full rounded-lg border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
    $fehler = 'mt-1 text-xs text-red-600 dark:text-red-400';
    $b = $bearbeiten;
@endphp
<form method="POST" action="{{ $b ? route('learner.exams.update', $b->pruefung_id) : route('learner.exams.store') }}"
      class="flex flex-col gap-4" x-data="{ loading: false, gewicht: @js((string) old('gewichtung_prozent', $b?->gewichtung_prozent ?? 100)) }"
      @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($b)
        @method('PUT')
    @endif

    @if($b && $b->quelle === \App\Models\Pruefung::ICAL && filled($b->lokal_gesperrt))
        @php
            // Anzeige der vom Kalender gemeldeten Werte für gesperrte Felder (siehe PruefungenController::quellwertFuerSperre()).
            $feldLabels = [
                'fach_id' => __('Zuordnung'), 'modul_id' => __('Zuordnung'), 'titel' => __('Titel'),
                'datum' => __('Datum'), 'uhrzeit' => __('Uhrzeit'), 'dauer_minuten' => __('Dauer (Min.)'),
                'pruefungsart' => __('Prüfungsart'), 'hilfsmittel' => __('Erlaubte Hilfsmittel'),
                'stoff' => __('Prüfungsstoff'), 'raum' => __('Raum'), 'gewichtung_prozent' => __('Gewichtung %'),
            ];
            $lokalListe = [];
            foreach ($b->lokal_gesperrt as $feld => $quellwert) {
                $wert = match ($feld) {
                    'fach_id' => $quellwert !== null ? (\App\Models\Fach::find($quellwert)?->name ?? __('unbekannt')) : '–',
                    'modul_id' => $quellwert !== null ? (($m = \App\Models\Modul::find($quellwert)) ? trim($m->modul_nummer.' '.$m->titel) : __('unbekannt')) : '–',
                    'datum' => $quellwert ? \Carbon\CarbonImmutable::parse($quellwert)->format('d.m.Y') : '–',
                    'gewichtung_prozent' => \App\Support\Zahl::prozent($quellwert),
                    default => $quellwert !== null && $quellwert !== '' ? (string) $quellwert : '–',
                };
                $lokalListe[] = ['label' => $feldLabels[$feld] ?? $feld, 'wert' => $wert];
            }
        @endphp
        <div class="rounded-lg border border-border bg-bg/40 px-3 py-2.5 flex flex-col gap-2 text-xs">
            <p class="text-text font-medium">{{ __('Lokal angepasst') }}</p>
            <p class="text-muted">{{ __('Diese Felder wurden von Hand geändert und werden beim nächsten Abgleich nicht mehr vom Kalender überschrieben.') }}</p>
            <ul class="flex flex-col gap-0.5 text-muted">
                @foreach($lokalListe as $eintrag)
                    <li>{{ $eintrag['label'] }}: {{ __('Kalender meldet: :wert', ['wert' => $eintrag['wert']]) }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('learner.exams.unlock', $b->pruefung_id) }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <button :disabled="loading" class="inline-flex items-center px-3 h-9 rounded-lg glass-btn text-text text-xs disabled:opacity-60">{{ __('Wieder vom Kalender übernehmen') }}</button>
            </form>
        </div>
    @endif

    <div>
        <label for="bezug" class="{{ $label }}">{{ __('Fach / Modul') }} <span class="text-red-600 dark:text-red-400">*</span></label>
        <select id="bezug" name="bezug" required class="{{ $feld }}">
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
        <label for="titel" class="{{ $label }}">{{ __('Titel') }}</label>
        <input id="titel" name="titel" maxlength="150" value="{{ old('titel', $b?->titel) }}" class="{{ $feld }}">
        @error('titel')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="datum" class="{{ $label }}">{{ __('Datum') }} <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="date" id="datum" name="datum" required value="{{ old('datum', $b?->datum?->toDateString()) }}" class="{{ $feld }}">
            @error('datum')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="uhrzeit" class="{{ $label }}">{{ __('Uhrzeit') }}</label>
            <input type="time" id="uhrzeit" name="uhrzeit" value="{{ old('uhrzeit', $b?->uhrzeit ? substr((string) $b->uhrzeit, 0, 5) : null) }}" class="{{ $feld }}">
            @error('uhrzeit')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="gewichtung_prozent" class="{{ $label }}">{{ __('Gewichtung %') }}</label>
        <input type="number" id="gewichtung_prozent" name="gewichtung_prozent" min="0" max="100" step="0.01" required x-model="gewicht" class="{{ $feld }}">
        <div class="flex gap-1.5 mt-2">
            @foreach([25, 50, 100] as $g)
                <button type="button" @click="gewicht = '{{ $g }}'" class="min-h-9 min-w-12 px-3 rounded-lg border text-xs transition-colors"
                        :class="parseFloat(gewicht) === {{ $g }} ? 'border-accent/50 bg-accent/10 text-accent' : 'border-border text-muted hover:text-text'">{{ $g }}%</button>
            @endforeach
        </div>
        @error('gewichtung_prozent')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="pruefungsart" class="{{ $label }}">{{ __('Prüfungsart') }}</label>
            <input id="pruefungsart" name="pruefungsart" list="pruefungsart-optionen" maxlength="150" value="{{ old('pruefungsart', $b?->pruefungsart) }}" class="{{ $feld }}">
            <datalist id="pruefungsart-optionen">
                <option value="{{ __('Schriftlich') }}"><option value="{{ __('Mündlich') }}"><option value="{{ __('Praktisch') }}"><option value="{{ __('Online') }}">
            </datalist>
            @error('pruefungsart')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="dauer_minuten" class="{{ $label }}">{{ __('Dauer (Min.)') }}</label>
            <input type="number" id="dauer_minuten" name="dauer_minuten" min="1" max="600" value="{{ old('dauer_minuten', $b?->dauer_minuten) }}" class="{{ $feld }}">
            @error('dauer_minuten')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="hilfsmittel" class="{{ $label }}">{{ __('Erlaubte Hilfsmittel') }}</label>
        <input id="hilfsmittel" name="hilfsmittel" maxlength="255" value="{{ old('hilfsmittel', $b?->hilfsmittel) }}" class="{{ $feld }}">
        @error('hilfsmittel')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="stoff" class="{{ $label }}">{{ __('Prüfungsstoff') }}</label>
        <textarea id="stoff" name="stoff" rows="4" maxlength="5000" class="{{ $textarea }}">{{ old('stoff', $b?->stoff) }}</textarea>
        @error('stoff')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="notizen" class="{{ $label }}">{{ __('Eigene Notizen') }}</label>
        <textarea id="notizen" name="notizen" rows="3" maxlength="5000" class="{{ $textarea }}">{{ old('notizen', $b?->notizen) }}</textarea>
        @error('notizen')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    @if($b?->note)
        <div class="rounded-lg border border-border bg-bg/40 px-3 py-2.5 flex items-center justify-between gap-3">
            <span class="text-sm text-text">{{ __('Note') }}</span>
            <x-note :wert="$b->note->note_wert" variante="badge" />
        </div>
    @elseif($b)
        <a href="{{ route('learner.grades.create', ['pruefung' => $b->pruefung_id]) }}" class="inline-flex items-center justify-center h-10 rounded-lg glass-btn text-text text-sm">{{ __('Note eintragen') }}</a>
    @endif

    @if($b)
        <div class="flex flex-col gap-2">
            <span class="{{ $label }}">{{ __('Angehängte Dateien') }}</span>
            @forelse($b->dokumente as $d)
                <div class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
                    <a href="{{ route('learner.documents.show', $d->dokument_id) }}" class="text-sm text-accent-text hover:underline truncate">{{ $d->titel }}</a>
                    <form method="POST" action="{{ route('learner.documents.destroy', $d->dokument_id) }}" onsubmit="return confirm('{{ __('Anhang entfernen?') }}');">
                        @csrf @method('DELETE')
                        <button class="text-muted hover:text-red-600 dark:hover:text-red-400 text-sm px-1" aria-label="{{ __('Entfernen') }}">×</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('Keine Anhänge.') }}</p>
            @endforelse
            <form method="POST" action="{{ route('learner.documents.store') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="art" value="pruefung">
                <input type="hidden" name="pruefung_id" value="{{ $b->pruefung_id }}">
                <input type="file" name="datei" required class="text-sm text-muted flex-1 min-w-0" aria-label="{{ __('Datei anhängen') }}">
                <button class="px-3 h-9 rounded-lg glass-btn text-text text-sm shrink-0">{{ __('Anhängen') }}</button>
            </form>
            @error('datei')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    @else
        <p class="text-xs text-muted">{{ __('Anhänge können nach dem Speichern hinzugefügt werden.') }}</p>
    @endif

    <div class="flex gap-2 mt-2">
        <a href="{{ route('learner.exams.index') }}" class="inline-flex items-center justify-center px-4 h-11 rounded-xl glass-btn text-text text-sm">{{ __('Abbrechen') }}</a>
        <button :disabled="loading" class="flex-1 h-11 rounded-xl bg-accent text-white font-semibold np-btn-primary disabled:opacity-60">{{ $b ? __('Speichern') : __('Planen') }}</button>
    </div>
</form>
