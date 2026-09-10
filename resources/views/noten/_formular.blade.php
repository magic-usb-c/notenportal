{{-- Note erfassen/bearbeiten, für alle Rollen. Erwartet: $action, $bezugOptionen, $semesterListe, $zurueck, $vorschauUrl; optional $note, $pruefung, $submitLabel --}}
@php
    $note ??= null;
    $pruefung ??= null;
    $bezug = old('bezug', $note
        ? ($note->fach_id ? 'fach:'.$note->fach_id : 'modul:'.$note->modulBelegung?->modul_id)
        : ($pruefung?->bezug() ?? $vorauswahl ?? null));
    $datum = old('pruefungsdatum', $note?->pruefungsdatum?->toDateString() ?? $pruefung?->datum?->toDateString() ?? now()->toDateString());
    $gewicht = (string) old('gewichtung_prozent', $note?->gewichtung_prozent ?? $pruefung?->gewichtung_prozent ?? 100);
    $label = 'text-xs uppercase tracking-widest text-muted font-medium';
    $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2.5 focus:ring-2 focus:ring-ring focus:border-ring';
    $fehler = 'mt-1 text-xs text-red-600 dark:text-red-400';
@endphp

<form method="POST" action="{{ $action }}" class="flex flex-col gap-6"
      x-data="npNotenFormular(@js([
          'bezug' => (string) $bezug,
          'datum' => $datum,
          'gewicht' => $gewicht,
          'wert' => (string) old('note_wert', $note?->note_wert ?? ''),
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
    <input type="hidden" name="typ" :value="typ">
    <input type="hidden" name="fach_id" :value="typ === 'fach' ? id : ''">
    <input type="hidden" name="modul_id" :value="typ === 'modul' ? id : ''">

    <div class="flex flex-col items-center gap-2">
        <label for="note_wert" class="{{ $label }}">Note <span class="text-red-600 dark:text-red-400">*</span></label>
        <input type="number" id="note_wert" name="note_wert" step="0.05" min="1" max="6" required autofocus
               x-model="wert" :class="klasse(wert)"
               class="w-36 h-20 text-4xl font-extrabold text-center tabular-nums rounded-2xl border-2 border-border bg-input focus:border-accent focus:outline-hidden focus:ring-0">
        @error('note_wert')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="bezug" class="{{ $label }}">Fach / Modul <span class="text-red-600 dark:text-red-400">*</span></label>
        <select id="bezug" name="bezug" x-model="bezug" required class="{{ $feld }}">
            <option value="">Bitte wählen</option>
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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="pruefungsdatum" class="{{ $label }}">Prüfungsdatum <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="date" id="pruefungsdatum" name="pruefungsdatum" required x-model="datum" class="{{ $feld }}">
            <p class="mt-1 text-xs" :class="semester ? 'text-muted' : 'text-orange-700 dark:text-orange-400'"
               x-text="semester ? 'Semester ' + semester.name : (datum ? 'Kein Semester für dieses Datum' : '')"></p>
            @error('pruefungsdatum')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="gewichtung_prozent" class="{{ $label }}">Gewichtung %</label>
            <input type="number" id="gewichtung_prozent" name="gewichtung_prozent" step="0.01" min="0" max="100" x-model="gewicht" class="{{ $feld }}">
            <div class="mt-1.5 flex gap-1.5">
                @foreach([25, 50, 100] as $g)
                    <button type="button" @click="gewicht = '{{ $g }}'"
                            class="min-h-9 min-w-12 px-3 rounded-lg border text-xs transition-colors"
                            :class="parseFloat(gewicht) === {{ $g }} ? 'border-accent/50 bg-accent/10 text-accent' : 'border-border text-muted hover:text-text'">{{ $g }}%</button>
                @endforeach
            </div>
            @error('gewichtung_prozent')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="titel" class="{{ $label }}">Titel</label>
        <input id="titel" name="titel" maxlength="150" value="{{ old('titel', $note?->titel ?? $pruefung?->titel) }}" class="{{ $feld }}">
        @error('titel')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
    </div>

    <div x-show="vorschau.length" x-cloak class="rounded-2xl border border-border bg-bg/60 px-4 py-3" aria-live="polite">
        <div class="{{ $label }} mb-1.5">Auswirkung</div>
        <template x-for="z in vorschau" :key="z.text">
            <div class="flex items-center justify-between gap-3 text-sm py-1">
                <span class="truncate" :class="z.ist_ziel ? 'text-text font-medium' : 'text-muted'" x-text="z.label"></span>
                <span class="shrink-0 tabular-nums">
                    <span class="text-muted" x-text="fmt(z.vorher)"></span>
                    <span class="text-muted" aria-hidden="true">→</span>
                    <span class="font-bold" :class="klasse(z.nachher)" x-text="fmt(z.nachher)"></span>
                </span>
            </div>
        </template>
    </div>

    <div class="flex flex-col-reverse sm:flex-row gap-3 pt-1">
        <a href="{{ $zurueck }}" class="inline-flex items-center justify-center px-5 h-12 rounded-xl glass-btn text-text">Abbrechen</a>
        <button type="submit" :disabled="loading"
                class="flex-1 h-12 rounded-xl bg-accent text-white font-semibold np-btn-primary inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            {{ $submitLabel ?? ($note ? 'Speichern' : 'Note erfassen') }}
        </button>
    </div>
</form>
