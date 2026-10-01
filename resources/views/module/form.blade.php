@php
    $neu = ! $modul->exists;
    $nummerFest = ! $neu && ! auth()->user()->hasRole('Admin');
    // Hinweis und Fehler eines Felds gemeinsam für aria-describedby
    $beschrieben = fn (string $feld, bool $hinweis = true) => implode(' ', array_filter([$hinweis ? $feld.'-hinweis' : null, $errors->has($feld) ? $feld.'-fehler' : null]));
@endphp

<x-app-layout>
    <x-slot name="title">{{ $neu ? __('Modul anlegen') : __('Modul bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="$neu ? route('modules.index') : route('modules.show', $modul->modul_id)" :titel="$neu ? __('Modul anlegen') : __('Modul bearbeiten')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <form method="POST" action="{{ $neu ? route('modules.store') : route('modules.update', $modul->modul_id) }}"
                  class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @unless($neu)
                    @method('PUT')
                    {{-- Stand beim Öffnen: hat inzwischen jemand anderes ergänzt, bricht das Speichern ab. --}}
                    <input type="hidden" name="stand" value="{{ $modul->aktualisiert_am?->getTimestamp() }}">
                @endunless

                <section>
                    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Modul') }}</h2>
                    <div class="np-karte np-gruppe">
                        <x-einstellung :label="__('Modulnummer')" fuer="modul_nummer" name="modul_nummer">
                            <input type="text" id="modul_nummer" name="modul_nummer" value="{{ old('modul_nummer', $modul->modul_nummer) }}" required maxlength="50"
                                   placeholder="M100" spellcheck="false" @if($nummerFest) readonly @endif
                                   class="np-feld w-32 tabular-nums" @error('modul_nummer') aria-invalid="true" aria-describedby="modul_nummer-fehler" @enderror>
                        </x-einstellung>
                        <x-einstellung :label="__('Titel')" fuer="titel" name="titel">
                            <input type="text" id="titel" name="titel" value="{{ old('titel', $modul->titel) }}" required maxlength="255"
                                   class="np-feld w-80" @error('titel') aria-invalid="true" aria-describedby="titel-fehler" @enderror>
                        </x-einstellung>
                        <x-einstellung :label="__('Katalogversion')" fuer="version" name="version" :hinweis="__('Erzeugt den Verweis auf den Modulbaukasten.')">
                            <input type="text" id="version" name="version" value="{{ old('version', $modul->version) }}" inputmode="numeric" maxlength="2" placeholder="1"
                                   aria-describedby="{{ $beschrieben('version') }}"
                                   class="np-feld w-24 text-right tabular-nums" @error('version') aria-invalid="true" @enderror>
                        </x-einstellung>
                        <x-einstellung :label="__('Verweis')" fuer="link" name="link">
                            <input type="url" id="link" name="link" value="{{ old('link', $modul->link) }}" maxlength="500" placeholder="https://"
                                   class="np-feld w-80" @error('link') aria-invalid="true" aria-describedby="link-fehler" @enderror>
                        </x-einstellung>
                    </div>
                </section>

                <section>
                    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Inhalt') }}</h2>
                    <div class="np-karte np-gruppe">
                        <x-einstellung :label="__('Beschreibung')" fuer="beschreibung" name="beschreibung" gestapelt>
                            <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000" placeholder="{{ __('Optional') }}"
                                      class="np-feld" @error('beschreibung') aria-invalid="true" aria-describedby="beschreibung-fehler" @enderror>{{ old('beschreibung', $modul->beschreibung) }}</textarea>
                        </x-einstellung>
                        <x-einstellung :label="__('Handlungsziele und Handlungskompetenzen')" fuer="handlungsziele" name="handlungsziele" gestapelt
                                       :hinweis="__('Eine Zeile je Ziel, eine führende Nummer wird übernommen.').($neu ? '' : ' '.__('Leer lassen ändert die bestehenden Ziele nicht.'))">
                            <textarea id="handlungsziele" name="handlungsziele" rows="8" maxlength="8000" aria-describedby="{{ $beschrieben('handlungsziele') }}"
                                      placeholder="{{ __('1 Analysiert die Ausgangslage und leitet Anforderungen ab.') }}"
                                      class="np-feld" @error('handlungsziele') aria-invalid="true" @enderror>{{ old('handlungsziele', $ziele) }}</textarea>
                        </x-einstellung>
                    </div>
                    <p class="mt-2 px-1 text-xs text-muted">{{ auth()->user()->hasRole('Lernender') ? __('Was du hier ergänzt, steht sofort allen zur Verfügung. Deine Noten bleiben privat.') : __('Was du hier ergänzt, steht sofort allen zur Verfügung.') }}</p>
                </section>

                <x-formular-aktionen :abbrechen="$neu ? route('modules.index') : route('modules.show', $modul->modul_id)">{{ $neu ? __('Modul anlegen') : __('Änderungen speichern') }}</x-formular-aktionen>
            </form>
        </div>
    </div>
</x-app-layout>
