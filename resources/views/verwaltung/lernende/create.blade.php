<x-app-layout>
    <x-slot name="title">{{ __('Lernender erfassen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.index')" :titel="__('Lernender erfassen')" schmal>
        </x-seitenkopf>
    </x-slot>

    @php
        $feld = 'np-feld mt-1';
        $label = 'text-sm font-medium text-text';
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="max-w-3xl">
            <form method="POST" action="{{ route("{$bereich}.learners.store") }}"
                  class="np-karte p-6 space-y-5"
                  x-data="{ track: @js(old('track_typ', '')), loading: false }"
                  @submit="if (!$event.defaultPrevented) loading = true">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="vorname" class="{{ $label }}">{{ __('Vorname *') }}</label>
                        <input id="vorname" type="text" name="vorname" value="{{ old('vorname') }}" required maxlength="100" class="{{ $feld }}">
                        @error('vorname')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="nachname" class="{{ $label }}">{{ __('Nachname *') }}</label>
                        <input id="nachname" type="text" name="nachname" value="{{ old('nachname') }}" required maxlength="100" class="{{ $feld }}">
                        @error('nachname')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="{{ $label }}">{{ __('E-Mail *') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="{{ $feld }}">
                        @error('email')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="benutzername" class="{{ $label }}">{{ __('Benutzername *') }}</label>
                        <input id="benutzername" type="text" name="benutzername" value="{{ old('benutzername') }}" required maxlength="50" aria-describedby="benutzername-hilfe" class="{{ $feld }} tabular-nums">
                        <p id="benutzername-hilfe" class="mt-1 text-xs text-muted">{{ __('Buchstaben, Ziffern, Punkt, Unterstrich und Bindestrich.') }}</p>
                        @error('benutzername')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="border-t border-border pt-5 space-y-4">
                    <div>
                        <label for="lehrberuf_id" class="{{ $label }}">{{ __('Lehrberuf *') }}</label>
                        <select id="lehrberuf_id" name="lehrberuf_id" required class="{{ $feld }}">
                            <option value="">{{ __('Bitte wählen') }}</option>
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id') == $lb->lehrberuf_id)>{{ $lb->name }}</option>
                            @endforeach
                        </select>
                        @error('lehrberuf_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="lehrbeginn" class="{{ $label }}">{{ __('Lehrbeginn *') }}</label>
                            <input id="lehrbeginn" type="date" name="lehrbeginn" value="{{ old('lehrbeginn') }}" required class="{{ $feld }}">
                            @error('lehrbeginn')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lehrende" class="{{ $label }}">{{ __('Lehrende') }}</label>
                            <input id="lehrende" type="date" name="lehrende" value="{{ old('lehrende') }}" class="{{ $feld }}">
                            @error('lehrende')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="track_typ" class="{{ $label }}">{{ __('Schul-Track') }}</label>
                            <select id="track_typ" name="track_typ" x-model="track" class="{{ $feld }}">
                                <option value="">{{ __('Kein Track') }}</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                            @error('track_typ')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="track" x-cloak>
                            <label for="track_semester_id" class="{{ $label }}">{{ __('Startsemester *') }}</label>
                            <select id="track_semester_id" name="track_semester_id" :required="track !== ''" class="{{ $feld }}">
                                <option value="">{{ __('Bitte wählen') }}</option>
                                @foreach($semester as $s)
                                    <option value="{{ $s->semester_id }}" @selected(old('track_semester_id') == $s->semester_id)>{{ $s->bezeichnung }}</option>
                                @endforeach
                            </select>
                            @error('track_semester_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @isset($berufsbildnerListe)
                        <div>
                            <label for="berufsbildner_id" class="{{ $label }}">{{ __('Berufsbildner') }}</label>
                            <select id="berufsbildner_id" name="berufsbildner_id" class="{{ $feld }}">
                                <option value="">{{ __('Keiner') }}</option>
                                @foreach($berufsbildnerListe as $bb)
                                    <option value="{{ $bb->berufsbildner_id }}" @selected(old('berufsbildner_id') == $bb->berufsbildner_id)>
                                        {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                                    </option>
                                @endforeach
                            </select>
                            @error('berufsbildner_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    @endisset

                    <div>
                        <label for="bemerkung" class="{{ $label }}">{{ __('Bemerkung (intern)') }}</label>
                        <textarea id="bemerkung" name="bemerkung" rows="3" maxlength="5000" class="{{ $feld }}">{{ old('bemerkung') }}</textarea>
                        @error('bemerkung')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <x-formular-aktionen :abbrechen="route($bereich.'.learners.index')">{{ __('Lernender anlegen') }}</x-formular-aktionen>
            </form>
            </div>
        </div>
    </div>
</x-app-layout>
