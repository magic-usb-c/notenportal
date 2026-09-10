{{-- Gemeinsames Formular für Note erfassen/korrigieren. Erwartet: $action, $kategorien, $faecher, $module, optional $note --}}
@php
    $note ??= null;
    $typ = old('typ', $note ? ($note->fach_id ? 'fach' : 'modul') : 'fach');
    $modulId = old('modul_id', $note?->modulBelegung?->modul_id);
    $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
    $label = 'text-xs uppercase tracking-widest text-muted font-medium';
    $zurueck = route("{$bereich}.lernende.noten.index", $lernender->lernender_id);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5"
      x-data="{ loading: false, typ: @js($typ) }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($note)
        @method('PUT')
    @endif

    <div>
        <label for="kategorie_id" class="{{ $label }}">Kategorie *</label>
        <select name="kategorie_id" id="kategorie_id" required class="{{ $feld }}">
            @foreach($kategorien as $k)
                <option value="{{ $k->kategorie_id }}" @selected(old('kategorie_id', $note?->kategorie_id) == $k->kategorie_id)>{{ $k->name }}</option>
            @endforeach
        </select>
        @error('kategorie_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <fieldset>
        <legend class="{{ $label }}">Typ *</legend>
        <div class="mt-2 flex gap-6">
            <label class="flex items-center gap-2 min-h-[36px]">
                <input type="radio" name="typ" value="fach" x-model="typ" class="text-accent focus:ring-ring">
                <span class="text-text">Fach</span>
            </label>
            <label class="flex items-center gap-2 min-h-[36px]">
                <input type="radio" name="typ" value="modul" x-model="typ" class="text-accent focus:ring-ring">
                <span class="text-text">Modul</span>
            </label>
        </div>
        @error('typ')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </fieldset>

    <div x-show="typ === 'fach'">
        <label for="fach_id" class="{{ $label }}">Fach *</label>
        <select name="fach_id" id="fach_id" class="{{ $feld }}">
            <option value="">Bitte wählen</option>
            @foreach($faecher as $f)
                <option value="{{ $f->fach_id }}" @selected(old('fach_id', $note?->fach_id) == $f->fach_id)>{{ $f->name }}</option>
            @endforeach
        </select>
        @error('fach_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div x-show="typ === 'modul'" x-cloak>
        <label for="modul_id" class="{{ $label }}">Modul *</label>
        <select name="modul_id" id="modul_id" class="{{ $feld }}">
            <option value="">Bitte wählen</option>
            @foreach($module as $m)
                <option value="{{ $m->modul_id }}" @selected((int) $modulId === (int) $m->modul_id)>
                    {{ (int) ($m->has_open_belegung ?? 0) === 1 ? '★ ' : '' }}{{ $m->modul_nummer }} – {{ $m->titel }}
                </option>
            @endforeach
        </select>
        @error('modul_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="titel" class="{{ $label }}">Titel</label>
        <input name="titel" id="titel" maxlength="150" value="{{ old('titel', $note?->titel) }}" class="{{ $feld }}">
        @error('titel')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="pruefungsdatum" class="{{ $label }}">Prüfungsdatum *</label>
            <input type="date" name="pruefungsdatum" id="pruefungsdatum" required
                   value="{{ old('pruefungsdatum', $note?->pruefungsdatum?->format('Y-m-d') ?? now()->toDateString()) }}" class="{{ $feld }}">
            @error('pruefungsdatum')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="note_wert" class="{{ $label }}">Note *</label>
            <input type="number" name="note_wert" id="note_wert" step="0.05" min="1" max="6" required
                   value="{{ old('note_wert', $note?->note_wert) }}" class="{{ $feld }}">
            @error('note_wert')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div x-data="{ gewichtung: @js((string) old('gewichtung_prozent', $note?->gewichtung_prozent ?? 100)) }">
            <label for="gewichtung_prozent" class="{{ $label }}">Gewichtung %</label>
            <input type="number" name="gewichtung_prozent" id="gewichtung_prozent" step="0.01" min="0" max="100"
                   x-model="gewichtung" class="{{ $feld }}">
            <div class="mt-1.5 flex gap-1.5">
                @foreach([25, 50, 100] as $g)
                    <button type="button" @click="gewichtung = '{{ $g }}'"
                            class="px-2.5 min-h-[36px] rounded-lg border border-border text-xs text-muted hover:text-text hover:bg-bg">{{ $g }}%</button>
                @endforeach
            </div>
            @error('gewichtung_prozent')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" :disabled="loading"
                class="flex-1 h-12 rounded-xl bg-accent text-white font-semibold hover:opacity-90 active:scale-[0.97] transition-all duration-150 inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
            {{ $note ? 'Korrektur speichern' : 'Note erfassen' }}
        </button>
        <a href="{{ $zurueck }}" class="inline-flex items-center px-5 h-12 rounded-xl glass-btn text-text">Abbrechen</a>
    </div>
</form>
