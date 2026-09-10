<x-app-layout>
    <x-slot name="title">Lernender erfassen</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <nav class="text-xs text-muted flex items-center gap-1 mb-1" aria-label="Brotkrumen">
                    <a href="{{ route("{$bereich}.lernende.index") }}" class="hover:text-text transition-colors">Lernende</a>
                    <span class="text-muted/40">›</span>
                    <span class="text-text">Erfassen</span>
                </nav>
                <h2 class="font-semibold text-xl text-text">Lernender erfassen</h2>
            </div>
            <a href="{{ route("{$bereich}.lernende.index") }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">Zurück</a>
        </div>
    </x-slot>

    @php
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'text-xs uppercase tracking-widest text-muted font-medium';
    @endphp

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route("{$bereich}.lernende.store") }}"
                  class="glass rounded-2xl p-6 space-y-5"
                  x-data="{ track: @js(old('track_typ', '')), loading: false }"
                  @submit="if (!$event.defaultPrevented) loading = true">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="vorname" class="{{ $label }}">Vorname *</label>
                        <input id="vorname" type="text" name="vorname" value="{{ old('vorname') }}" required maxlength="100" class="{{ $feld }}">
                        @error('vorname')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="nachname" class="{{ $label }}">Nachname *</label>
                        <input id="nachname" type="text" name="nachname" value="{{ old('nachname') }}" required maxlength="100" class="{{ $feld }}">
                        @error('nachname')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="{{ $label }}">E-Mail *</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="{{ $feld }}">
                        @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="benutzername" class="{{ $label }}">Benutzername * <span class="normal-case tracking-normal">(Buchstaben, Ziffern)</span></label>
                        <input id="benutzername" type="text" name="benutzername" value="{{ old('benutzername') }}" required maxlength="50" class="{{ $feld }} font-mono">
                        @error('benutzername')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="border-t border-border pt-5 space-y-4">
                    <div>
                        <label for="lehrberuf_id" class="{{ $label }}">Lehrberuf *</label>
                        <select id="lehrberuf_id" name="lehrberuf_id" required class="{{ $feld }}">
                            <option value="">Bitte wählen</option>
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id') == $lb->lehrberuf_id)>{{ $lb->name }}</option>
                            @endforeach
                        </select>
                        @error('lehrberuf_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="lehrbeginn" class="{{ $label }}">Lehrbeginn *</label>
                            <input id="lehrbeginn" type="date" name="lehrbeginn" value="{{ old('lehrbeginn') }}" required class="{{ $feld }}">
                            @error('lehrbeginn')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lehrende" class="{{ $label }}">Lehrende</label>
                            <input id="lehrende" type="date" name="lehrende" value="{{ old('lehrende') }}" class="{{ $feld }}">
                            @error('lehrende')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="track_typ" class="{{ $label }}">Schul-Track</label>
                            <select id="track_typ" name="track_typ" x-model="track" class="{{ $feld }}">
                                <option value="">Kein Track</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                            @error('track_typ')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="track" x-cloak>
                            <label for="track_semester_id" class="{{ $label }}">Startsemester *</label>
                            <select id="track_semester_id" name="track_semester_id" :required="track !== ''" class="{{ $feld }}">
                                <option value="">Bitte wählen</option>
                                @foreach($semester as $s)
                                    <option value="{{ $s->semester_id }}" @selected(old('track_semester_id') == $s->semester_id)>{{ $s->bezeichnung }}</option>
                                @endforeach
                            </select>
                            @error('track_semester_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @isset($berufsbildnerListe)
                        <div>
                            <label for="berufsbildner_id" class="{{ $label }}">Berufsbildner</label>
                            <select id="berufsbildner_id" name="berufsbildner_id" class="{{ $feld }}">
                                <option value="">Keiner</option>
                                @foreach($berufsbildnerListe as $bb)
                                    <option value="{{ $bb->berufsbildner_id }}" @selected(old('berufsbildner_id') == $bb->berufsbildner_id)>
                                        {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                                    </option>
                                @endforeach
                            </select>
                            @error('berufsbildner_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @endisset

                    <div>
                        <label for="bemerkung" class="{{ $label }}">Bemerkung (intern)</label>
                        <textarea id="bemerkung" name="bemerkung" rows="3" maxlength="5000" class="{{ $feld }}">{{ old('bemerkung') }}</textarea>
                        @error('bemerkung')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <button type="submit" :disabled="loading"
                        class="w-full h-12 rounded-xl bg-accent text-white font-semibold hover:opacity-90 active:scale-[0.97] transition-all duration-150 inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    Lernender anlegen
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
