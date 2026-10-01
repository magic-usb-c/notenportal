{{--
    Note erfassen/bearbeiten, für alle Rollen. Erwartet: $action, $bezugOptionen, $semesterListe, $zurueck, $vorschauUrl;
    optional $note, $pruefung, $submitLabel, $drawer (Name des Drawer-Kontexts: «Abbrechen» schliesst den Drawer,
    ein Fehler öffnet ihn nach dem Absenden wieder)
--}}
@php
    $note ??= null;
    $pruefung ??= null;
    $drawer ??= null;
    $bezug = old('bezug', $note
        ? ($note->fach_id ? 'fach:'.$note->fach_id : 'modul:'.$note->modulBelegung?->modul_id)
        : ($pruefung?->bezug() ?? $vorauswahl ?? null));
    $datum = old('pruefungsdatum', $note?->pruefungsdatum?->toDateString() ?? $pruefung?->datum?->toDateString() ?? now()->toDateString());
    $gewicht = (string) old('gewichtung_prozent', $note?->gewichtung_prozent ?? $pruefung?->gewichtung_prozent ?? 100);
    $label = 'text-sm font-medium text-text';
    $feld = 'np-feld mt-1.5';
    $fehler = 'mt-1 text-xs text-note-ungenuegend';
    $bezugFehler = array_values(array_filter(['typ', 'fach_id', 'modul_id'], fn ($f) => $errors->has($f)));
@endphp

<form method="POST" action="{{ $action }}" class="flex flex-col gap-6" novalidate
      x-data="npNotenFormular(@js([
          'bezug' => (string) $bezug,
          'datum' => $datum,
          'gewicht' => $gewicht,
          'wert' => (string) old('note_wert', $note?->note_wert ?? ''),
          'stufe' => (string) old('note_stufe', $note?->note_stufe ?? ''),
          'stufen' => collect($bezugOptionen)->flatten(1)->where('skala', 'stufe')->pluck('wert')->values()->all(),
          'semester' => $semesterListe,
          'vorschauUrl' => $vorschauUrl,
          'ersetzt' => $note?->note_id,
          'grenzen' => \App\Support\NotenSkala::grenzen(),
      ]))"
      @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($note)
        @method('PUT')
    @endif
    @if($pruefung)
        <input type="hidden" name="pruefung_id" value="{{ $pruefung->pruefung_id }}">
    @endif
    @if($drawer)
        <input type="hidden" name="_drawer" value="{{ $drawer }}">
    @endif
    <input type="hidden" name="typ" :value="typ">
    <input type="hidden" name="fach_id" :value="typ === 'fach' ? id : ''">
    <input type="hidden" name="modul_id" :value="typ === 'modul' ? id : ''">

    <div class="flex flex-col items-center gap-2" x-show="!istStufe">
        <label for="note_wert" class="{{ $label }}">{{ __('Note') }}</label>
        <input type="number" id="note_wert" name="note_wert" step="0.05" min="1" max="6" required autofocus
               x-model="wert" :class="klasse(wert)" :disabled="istStufe" @error('note_wert') aria-invalid="true" aria-describedby="note_wert-fehler" @enderror
               class="np-feld h-20 w-36 border-2 text-center text-3xl font-semibold tabular-nums">
        @error('note_wert')<p id="note_wert-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <fieldset class="flex flex-col items-center gap-2" x-show="istStufe" x-cloak @error('note_stufe') aria-describedby="note_stufe-fehler" @enderror>
        <legend class="{{ $label }} mb-2 text-center">{{ __('Stufe') }}</legend>
        <div class="inline-flex gap-0.5 rounded-2xl bg-fill p-1" role="radiogroup">
            @foreach(\App\Services\Noten\NoteService::STUFEN as $s)
                <label class="relative">
                    <input type="radio" name="note_stufe" value="{{ $s }}" x-model="stufe" :disabled="!istStufe" class="peer sr-only">
                    <span class="flex h-14 min-w-14 cursor-pointer items-center justify-center rounded-xl px-4 text-2xl font-semibold text-muted transition-colors duration-150 hover:text-text peer-checked:bg-accent peer-checked:text-accent-contrast peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring">
                        {{ \App\Support\NotenSkala::stufeKurz($s) }}
                    </span>
                </label>
            @endforeach
        </div>
        @error('note_stufe')<p id="note_stufe-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </fieldset>

    <div>
        <label for="bezug" class="{{ $label }}">{{ __('Fach / Modul') }}</label>
        <select id="bezug" name="bezug" x-model="bezug" required class="{{ $feld }}" @if($bezugFehler) aria-invalid="true" aria-describedby="{{ implode(' ', array_map(fn ($f) => $f.'-fehler', $bezugFehler)) }}" @endif>
            <option value="">{{ __('Bitte wählen…') }}</option>
            @foreach($bezugOptionen as $gruppe => $optionen)
                <optgroup label="{{ $gruppe }}">
                    @foreach($optionen as $o)
                        <option value="{{ $o['wert'] }}" @selected($bezug === $o['wert'])>{{ $o['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @foreach($bezugFehler as $f)
            <p id="{{ $f }}-fehler" class="{{ $fehler }}">{{ $errors->first($f) }}</p>
        @endforeach
    </div>

    {{-- Eine Spalte: im Drawer (28rem) hätten Datum und Gewichtung mit ihren Segmenten nebeneinander keinen Platz --}}
    <div>
        <label for="pruefungsdatum" class="{{ $label }}">{{ __('Prüfungsdatum') }}</label>
        <div class="mt-1.5 flex items-center gap-3">
            <input type="date" id="pruefungsdatum" name="pruefungsdatum" required x-model="datum" class="np-feld w-48 tabular-nums"
                   aria-describedby="pruefungsdatum-hinweis @error('pruefungsdatum') pruefungsdatum-fehler @enderror" @error('pruefungsdatum') aria-invalid="true" @enderror>
            <p id="pruefungsdatum-hinweis" class="text-xs" :class="semester ? 'text-muted' : 'text-note-knapp'"
               x-text="semester ? semester.name : (datum ? @js(__('Kein Semester für dieses Datum')) : '')"></p>
        </div>
        @error('pruefungsdatum')<p id="pruefungsdatum-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="gewichtung_prozent" class="{{ $label }}">{{ __('Gewichtung') }}</label>
        <x-gewicht-feld id="gewichtung_prozent" model="gewicht"
                        :aria-invalid="$errors->has('gewichtung_prozent') ? 'true' : null"
                        :aria-describedby="$errors->has('gewichtung_prozent') ? 'gewichtung_prozent-fehler' : null" />
        @error('gewichtung_prozent')<p id="gewichtung_prozent-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="titel" class="{{ $label }}">{{ __('Titel') }}</label>
        <input id="titel" name="titel" maxlength="150" value="{{ old('titel', $note?->titel ?? $pruefung?->titel) }}" placeholder="{{ __('Optional') }}"
               class="{{ $feld }}" @error('titel') aria-invalid="true" aria-describedby="titel-fehler" @enderror>
        @error('titel')<p id="titel-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <section x-show="vorschau.length" x-cloak aria-live="polite">
        <h2 class="mb-2 px-1 text-xs font-medium text-muted">{{ __('Auswirkung') }}</h2>
        {{-- Fläche statt Karte: steht im Drawer wie in der Formularkarte; divide-y, weil das <template> erstes Kind ist --}}
        <div class="divide-y divide-border rounded-xl bg-fill-2 px-4">
            <template x-for="z in vorschau" :key="z.text">
                <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                    <span class="truncate" :class="z.ist_ziel ? 'text-text font-medium' : 'text-muted'" x-text="z.label"></span>
                    <span class="shrink-0 tabular-nums">
                        <span class="text-muted" x-text="fmt(z.vorher)"></span>
                        <span class="text-muted" aria-hidden="true">→</span>
                        <span class="font-semibold" :class="klasse(z.nachher)" x-text="fmt(z.nachher)"></span>
                    </span>
                </div>
            </template>
        </div>
    </section>

    <x-formular-aktionen :abbrechen="$zurueck" :schliessen="$drawer ? 'note' : null">
        {{ $submitLabel ?? ($note ? __('Speichern') : __('Note erfassen')) }}
    </x-formular-aktionen>
</form>
