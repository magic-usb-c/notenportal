@php
    $neu = ! $modul->exists;
    $feld = 'np-feld mt-1';
    $label = 'text-sm font-medium text-text';
@endphp

<x-app-layout>
    <x-slot name="title">{{ $neu ? __('Modul anlegen') : __('Modul bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="$neu ? route('modules.index') : route('modules.show', $modul->modul_id)" :titel="$neu ? __('Modul anlegen') : __('Modul bearbeiten')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ $neu ? route('modules.store') : route('modules.update', $modul->modul_id) }}"
                      class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @unless($neu)
                        @method('PUT')
                        {{-- Stand beim Öffnen: hat inzwischen jemand anderes ergänzt, bricht das Speichern ab. --}}
                        <input type="hidden" name="stand" value="{{ $modul->aktualisiert_am?->getTimestamp() }}">
                    @endunless

                    <div>
                        <label for="modul_nummer" class="{{ $label }}">{{ __('Modulnummer') }} *</label>
                        <input type="text" id="modul_nummer" name="modul_nummer" value="{{ old('modul_nummer', $modul->modul_nummer) }}" required maxlength="50" placeholder="M100"
                               @unless($neu || auth()->user()->hasRole('Admin')) readonly @endunless
                               class="{{ $feld }} max-w-48 tabular-nums @error('modul_nummer') border-note-ungenuegend @enderror">
                        @unless($neu || auth()->user()->hasRole('Admin'))
                            <p class="mt-1 text-xs text-muted">{{ __('Die Nummer bleibt fest: an ihr hängen die Noten aller, die dieses Modul führen.') }}</p>
                        @endunless
                        @error('modul_nummer')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="titel" class="{{ $label }}">{{ __('Titel') }} *</label>
                        <input type="text" id="titel" name="titel" value="{{ old('titel', $modul->titel) }}" required maxlength="255"
                               class="{{ $feld }} @error('titel') border-note-ungenuegend @enderror">
                        @error('titel')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="version" class="{{ $label }}">{{ __('Katalogversion') }}</label>
                        <input type="text" id="version" name="version" value="{{ old('version', $modul->version) }}" inputmode="numeric" maxlength="2" placeholder="1" aria-describedby="version-hilfe"
                               class="{{ $feld }} max-w-24 tabular-nums @error('version') border-note-ungenuegend @enderror">
                        <p id="version-hilfe" class="mt-1 text-xs text-muted">{{ __('Erzeugt den Verweis auf den Modulbaukasten.') }}</p>
                        @error('version')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="link" class="{{ $label }}">{{ __('Verweis') }}</label>
                        <input type="url" id="link" name="link" value="{{ old('link', $modul->link) }}" maxlength="500" placeholder="https://" aria-describedby="link-hilfe"
                               class="{{ $feld }} @error('link') border-note-ungenuegend @enderror">
                        <p id="link-hilfe" class="mt-1 text-xs text-muted">{{ __('Etwa die Modulseite der Schule.') }}</p>
                        @error('link')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="beschreibung" class="{{ $label }}">{{ __('Beschreibung') }}</label>
                        <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000"
                                  class="{{ $feld }} @error('beschreibung') border-note-ungenuegend @enderror">{{ old('beschreibung', $modul->beschreibung) }}</textarea>
                        @error('beschreibung')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="handlungsziele" class="{{ $label }}">{{ __('Handlungsziele und Handlungskompetenzen') }}</label>
                        <textarea id="handlungsziele" name="handlungsziele" rows="8" maxlength="8000" aria-describedby="handlungsziele-hilfe"
                                  placeholder="{{ __('1 Analysiert die Ausgangslage und leitet Anforderungen ab.') }}"
                                  class="{{ $feld }} @error('handlungsziele') border-note-ungenuegend @enderror">{{ old('handlungsziele', $ziele) }}</textarea>
                        <p id="handlungsziele-hilfe" class="mt-1 text-xs text-muted">{{ __('Eine Zeile je Ziel, eine führende Nummer wird übernommen.') }}@unless($neu) {{ __('Leer lassen ändert die bestehenden Ziele nicht.') }}@endunless</p>
                        @error('handlungsziele')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <x-formular-aktionen :abbrechen="$neu ? route('modules.index') : route('modules.show', $modul->modul_id)">{{ $neu ? __('Modul anlegen') : __('Änderungen speichern') }}</x-formular-aktionen>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
