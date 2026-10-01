@php
    $label = 'text-sm font-medium text-text';
    $feld = 'np-feld mt-1.5';
    $textarea = 'np-feld mt-1.5';
    $fehler = 'mt-1 text-xs text-note-ungenuegend';
    $b = $bearbeiten;
@endphp
<form method="POST" action="{{ $b ? route('learner.exams.update', $b->pruefung_id) : route('learner.exams.store') }}"
      class="flex flex-col gap-4" x-data="{ loading: false, gewicht: @js((string) old('gewichtung_prozent', $b?->gewichtung_prozent ?? 100)) }"
      @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($b)
        @method('PUT')
    @else
        <input type="hidden" name="_drawer" value="pruefung">
    @endif

    @if($b && $b->quelle === \App\Models\Pruefung::ICAL && filled($b->lokal_gesperrt))
        @php
            // Anzeige der vom Kalender gemeldeten Werte für gesperrte Felder (siehe PruefungenController::quellwertFuerSperre()).
            $feldLabels = [
                'fach_id' => __('Zuordnung'), 'modul_id' => __('Zuordnung'), 'titel' => __('Titel'),
                'datum' => __('Datum'), 'uhrzeit' => __('Uhrzeit'), 'dauer_minuten' => __('Dauer (Min.)'),
                'pruefungsart' => __('Prüfungsart'), 'hilfsmittel' => __('Erlaubte Hilfsmittel'),
                'stoff' => __('Prüfungsstoff'), 'gewichtung_prozent' => __('Gewichtung'),
            ];
            $lokalListe = [];
            // Schleifenvariable nicht «$feld»: das würde die Feldklasse von oben überschreiben
            foreach ($b->lokal_gesperrt as $gesperrt => $quellwert) {
                $wert = match ($gesperrt) {
                    'fach_id' => $quellwert !== null ? (\App\Models\Fach::find($quellwert)?->name ?? __('unbekannt')) : '–',
                    'modul_id' => $quellwert !== null ? (($m = \App\Models\Modul::find($quellwert)) ? trim($m->modul_nummer.' '.$m->titel) : __('unbekannt')) : '–',
                    'datum' => $quellwert ? \Carbon\CarbonImmutable::parse($quellwert)->format('d.m.Y') : '–',
                    'gewichtung_prozent' => \App\Support\Zahl::prozent($quellwert),
                    default => $quellwert !== null && $quellwert !== '' ? (string) $quellwert : '–',
                };
                $lokalListe[] = ['label' => $feldLabels[$gesperrt] ?? $gesperrt, 'wert' => $wert];
            }
        @endphp
        <div class="flex flex-col gap-2 rounded-xl bg-fill-2 px-4 py-3 text-xs">
            <p class="text-text font-medium">{{ __('Lokal angepasst') }}</p>
            <p class="text-muted">{{ __('Der Kalender überschreibt diese Felder nicht mehr.') }}</p>
            <ul class="flex flex-col gap-0.5 text-muted">
                @foreach($lokalListe as $eintrag)
                    <li>{{ $eintrag['label'] }}: {{ __('Kalender meldet: :wert', ['wert' => $eintrag['wert']]) }}</li>
                @endforeach
            </ul>
            <div>
                <button form="pruefung-entsperren" class="np-knopf np-knopf-sekundaer np-knopf-klein"
                        x-data="{ loading: false }" :disabled="loading"
                        @submit.window="if ($event.target.id === 'pruefung-entsperren' && ! $event.defaultPrevented) loading = true"
                        @pageshow.window="loading = false">{{ __('Wieder vom Kalender übernehmen') }}</button>
            </div>
        </div>
    @endif

    <div>
        <label for="bezug" class="{{ $label }}">{{ __('Fach / Modul') }}</label>
        <select id="bezug" name="bezug" required class="{{ $feld }}" @error('bezug') aria-invalid="true" aria-describedby="bezug-fehler" @enderror>
            <option value="">{{ __('Bitte wählen…') }}</option>
            @foreach($bezugOptionen as $gruppe => $optionen)
                <optgroup label="{{ $gruppe }}">
                    @foreach($optionen as $o)
                        <option value="{{ $o['wert'] }}" @selected(old('bezug', $b?->bezug()) === $o['wert'])>{{ $o['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('bezug')<p id="bezug-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="titel" class="{{ $label }}">{{ __('Titel') }}</label>
        <input id="titel" name="titel" maxlength="150" value="{{ old('titel', $b?->titel) }}" placeholder="{{ __('Optional') }}"
               class="{{ $feld }}" @error('titel') aria-invalid="true" aria-describedby="titel-fehler" @enderror>
        @error('titel')<p id="titel-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="datum" class="{{ $label }}">{{ __('Datum') }}</label>
            <input type="date" id="datum" name="datum" required value="{{ old('datum', $b?->datum?->toDateString()) }}"
                   class="{{ $feld }} tabular-nums" @error('datum') aria-invalid="true" aria-describedby="datum-fehler" @enderror>
            @error('datum')<p id="datum-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="uhrzeit" class="{{ $label }}">{{ __('Uhrzeit') }}</label>
            <input type="time" id="uhrzeit" name="uhrzeit" value="{{ old('uhrzeit', $b?->uhrzeit ? substr((string) $b->uhrzeit, 0, 5) : null) }}"
                   class="{{ $feld }} tabular-nums" @error('uhrzeit') aria-invalid="true" aria-describedby="uhrzeit-fehler" @enderror>
            @error('uhrzeit')<p id="uhrzeit-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="gewichtung_prozent" class="{{ $label }}">{{ __('Gewichtung') }}</label>
        <x-gewicht-feld id="gewichtung_prozent" model="gewicht" required
                        :aria-invalid="$errors->has('gewichtung_prozent') ? 'true' : null"
                        :aria-describedby="$errors->has('gewichtung_prozent') ? 'gewichtung_prozent-fehler' : null" />
        @error('gewichtung_prozent')<p id="gewichtung_prozent-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="pruefungsart" class="{{ $label }}">{{ __('Prüfungsart') }}</label>
            <input id="pruefungsart" name="pruefungsart" list="pruefungsart-optionen" maxlength="150" value="{{ old('pruefungsart', $b?->pruefungsart) }}" placeholder="{{ __('Optional') }}"
                   class="{{ $feld }}" @error('pruefungsart') aria-invalid="true" aria-describedby="pruefungsart-fehler" @enderror>
            <datalist id="pruefungsart-optionen">
                <option value="{{ __('Schriftlich') }}"><option value="{{ __('Mündlich') }}"><option value="{{ __('Praktisch') }}"><option value="{{ __('Online') }}">
            </datalist>
            @error('pruefungsart')<p id="pruefungsart-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="dauer_minuten" class="{{ $label }}">{{ __('Dauer (Min.)') }}</label>
            <input type="number" id="dauer_minuten" name="dauer_minuten" min="1" max="600" value="{{ old('dauer_minuten', $b?->dauer_minuten) }}" placeholder="{{ __('Optional') }}"
                   class="{{ $feld }} text-right tabular-nums" @error('dauer_minuten') aria-invalid="true" aria-describedby="dauer_minuten-fehler" @enderror>
            @error('dauer_minuten')<p id="dauer_minuten-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="hilfsmittel" class="{{ $label }}">{{ __('Erlaubte Hilfsmittel') }}</label>
        <input id="hilfsmittel" name="hilfsmittel" maxlength="255" value="{{ old('hilfsmittel', $b?->hilfsmittel) }}" placeholder="{{ __('Optional') }}"
               class="{{ $feld }}" @error('hilfsmittel') aria-invalid="true" aria-describedby="hilfsmittel-fehler" @enderror>
        @error('hilfsmittel')<p id="hilfsmittel-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="stoff" class="{{ $label }}">{{ __('Prüfungsstoff') }}</label>
        <textarea id="stoff" name="stoff" rows="4" maxlength="5000" placeholder="{{ __('Optional') }}" class="{{ $textarea }}"
                  @error('stoff') aria-invalid="true" aria-describedby="stoff-fehler" @enderror>{{ old('stoff', $b?->stoff) }}</textarea>
        @error('stoff')<p id="stoff-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="notizen" class="{{ $label }}">{{ __('Eigene Notizen') }}</label>
        <textarea id="notizen" name="notizen" rows="3" maxlength="5000" placeholder="{{ __('Optional') }}" class="{{ $textarea }}"
                  @error('notizen') aria-invalid="true" aria-describedby="notizen-fehler" @enderror>{{ old('notizen', $b?->notizen) }}</textarea>
        @error('notizen')<p id="notizen-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    @if($b?->note)
        <div class="flex items-center justify-between gap-3 rounded-xl bg-fill-2 px-4 py-2.5">
            <span class="text-sm text-text">{{ __('Note') }}</span>
            <x-note :wert="$b->note->note_wert" :stufe="$b->note->note_stufe" variante="badge" />
        </div>
    @elseif($b)
        <a href="{{ route('learner.grades.create', ['pruefung' => $b->pruefung_id]) }}" class="np-knopf np-knopf-sekundaer">{{ __('Note eintragen') }}</a>
    @endif

    @if($b)
        <div class="flex flex-col gap-2">
            <span id="anhaenge-bez" class="{{ $label }}">{{ __('Angehängte Dateien') }}</span>
            @forelse($b->dokumente as $d)
                <div class="flex items-center justify-between gap-2 rounded-xl bg-fill-2 py-1 pl-4 pr-1">
                    <a href="{{ route('learner.documents.show', $d->dokument_id) }}" class="truncate text-sm text-accent-text hover:underline">{{ $d->titel }}</a>
                    <button form="anhang-entfernen-{{ $d->dokument_id }}" class="np-knopf np-knopf-symbol np-knopf-klein" aria-label="{{ __(':titel entfernen', ['titel' => $d->titel]) }}"
                            x-data="{ loading: false }" :disabled="loading"
                            @submit.window="if ($event.target.id === 'anhang-entfernen-{{ $d->dokument_id }}' && ! $event.defaultPrevented) loading = true"
                            @pageshow.window="loading = false"><x-symbol name="x-mark" class="size-4" /></button>
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('Keine Anhänge.') }}</p>
            @endforelse
            <div class="flex items-center gap-2">
                <x-datei-feld id="anhang-datei" rahmen="flex-1" name="datei" form="anhang-hochladen" required :aria-label="__('Datei anhängen')"
                              :aria-invalid="$errors->has('datei') ? 'true' : null" :aria-describedby="$errors->has('datei') ? 'datei-fehler' : null" />
                <button form="anhang-hochladen" class="np-knopf np-knopf-sekundaer shrink-0"
                        x-data="{ loading: false }" :disabled="loading"
                        @submit.window="if ($event.target.id === 'anhang-hochladen' && ! $event.defaultPrevented) loading = true"
                        @pageshow.window="loading = false">{{ __('Anhängen') }}</button>
            </div>
            @error('datei')<p id="datei-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    @endif

    <x-formular-aktionen schliessen="pruefung">{{ $b ? __('Speichern') : __('Planen') }}</x-formular-aktionen>
</form>

{{-- Eigenständige Formulare ausserhalb des Hauptformulars: HTML erlaubt keine verschachtelten
     <form>, die Knöpfe oben hängen sich über das form-Attribut an. --}}
@if($b)
    @if($b->quelle === \App\Models\Pruefung::ICAL && filled($b->lokal_gesperrt))
        <form id="pruefung-entsperren" method="POST" action="{{ route('learner.exams.unlock', $b->pruefung_id) }}" class="hidden">
            @csrf
        </form>
    @endif
    @foreach($b->dokumente as $d)
        <form id="anhang-entfernen-{{ $d->dokument_id }}" method="POST" action="{{ route('learner.documents.destroy', $d->dokument_id) }}" data-bestaetigen="{{ __('Anhang entfernen?') }}" data-bestaetigen-knopf="{{ __('Entfernen') }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach
    <form id="anhang-hochladen" method="POST" action="{{ route('learner.documents.store') }}" enctype="multipart/form-data" class="hidden">
        @csrf
        <input type="hidden" name="art" value="pruefung">
        <input type="hidden" name="pruefung_id" value="{{ $b->pruefung_id }}">
    </form>
@endif
