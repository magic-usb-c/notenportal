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
        <label for="note_wert" class="{{ $label }}">{{ __('Note') }} <span class="text-note-ungenuegend">*</span></label>
        <input type="number" id="note_wert" name="note_wert" step="0.05" min="1" max="6" required autofocus
               x-model="wert" :class="klasse(wert)" :disabled="istStufe" @error('note_wert') aria-describedby="note_wert-fehler" @enderror
               class="np-feld h-20 w-36 border-2 text-center text-4xl font-semibold tabular-nums">
        @error('note_wert')<p id="note_wert-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <fieldset class="flex flex-col items-center gap-2" x-show="istStufe" x-cloak>
        <legend class="{{ $label }} mb-2 text-center">{{ __('Stufe') }} <span class="text-note-ungenuegend">*</span></legend>
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
        @error('note_stufe')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </fieldset>

    <div>
        <label for="bezug" class="{{ $label }}">{{ __('Fach / Modul') }} <span class="text-note-ungenuegend">*</span></label>
        <select id="bezug" name="bezug" x-model="bezug" required class="{{ $feld }}">
            <option value="">{{ __('Bitte wählen') }}</option>
            @foreach($bezugOptionen as $gruppe => $optionen)
                <optgroup label="{{ $gruppe }}">
                    @foreach($optionen as $o)
                        <option value="{{ $o['wert'] }}" @selected($bezug === $o['wert'])>{{ $o['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @foreach(['typ', 'fach_id', 'modul_id'] as $f)
            @error($f)<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="pruefungsdatum" class="{{ $label }}">{{ __('Prüfungsdatum') }} <span class="text-note-ungenuegend">*</span></label>
            <input type="date" id="pruefungsdatum" name="pruefungsdatum" required x-model="datum" class="{{ $feld }}">
            <p class="mt-1 text-xs" :class="semester ? 'text-muted' : 'text-note-knapp'"
               x-text="semester ? @js(__('Semester ')) + semester.name : (datum ? @js(__('Kein Semester für dieses Datum')) : '')"></p>
            @error('pruefungsdatum')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="gewichtung_prozent" class="{{ $label }}">{{ __('Gewichtung %') }}</label>
            <input type="number" id="gewichtung_prozent" name="gewichtung_prozent" step="0.01" min="0" max="100" x-model="gewicht" class="{{ $feld }}">
            <div class="mt-1.5 flex gap-1.5">
                @foreach([25, 50, 100] as $g)
                    <button type="button" @click="gewicht = '{{ $g }}'"
                            class="h-9 min-w-12 rounded-lg border px-3 text-xs transition-colors duration-100"
                            :class="parseFloat(gewicht) === {{ $g }} ? 'border-accent/50 bg-accent/10 text-accent-text' : 'border-border-strong/60 text-muted hover:text-text'">{{ $g }}%</button>
                @endforeach
            </div>
            @error('gewichtung_prozent')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="titel" class="{{ $label }}">{{ __('Titel') }}</label>
        <input id="titel" name="titel" maxlength="150" value="{{ old('titel', $note?->titel ?? $pruefung?->titel) }}" class="{{ $feld }}">
        @error('titel')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div x-show="vorschau.length" x-cloak class="rounded-xl border border-border bg-surface-2/60 px-4 py-3" aria-live="polite">
        <div class="mb-1.5 text-xs font-medium text-muted">{{ __('Auswirkung') }}</div>
        <template x-for="z in vorschau" :key="z.text">
            <div class="flex items-center justify-between gap-3 py-1 text-sm">
                <span class="truncate" :class="z.ist_ziel ? 'text-text font-medium' : 'text-muted'" x-text="z.label"></span>
                <span class="shrink-0 tabular-nums">
                    <span class="text-muted" x-text="fmt(z.vorher)"></span>
                    <span class="text-muted" aria-hidden="true">→</span>
                    <span class="font-semibold" :class="klasse(z.nachher)" x-text="fmt(z.nachher)"></span>
                </span>
            </div>
        </template>
    </div>

    <div class="flex items-center justify-end gap-2 pt-2">
        @if($drawer)
            <button type="button" @click="$dispatch('close-drawer', 'note')" class="np-knopf np-knopf-sekundaer min-w-24">{{ __('Abbrechen') }}</button>
        @else
            <a href="{{ $zurueck }}" class="np-knopf np-knopf-sekundaer min-w-24">{{ __('Abbrechen') }}</a>
        @endif
        <button type="submit" :disabled="loading"
                class="np-knopf np-knopf-primaer min-w-24">
            <svg x-show="loading" x-cloak class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            {{ $submitLabel ?? ($note ? __('Speichern') : __('Note erfassen')) }}
        </button>
    </div>
</form>
