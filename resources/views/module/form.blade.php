@php
    $neu = ! $modul->exists;
    $nummerFest = ! $neu && ! auth()->user()->hasRole('Admin');
    // Hinweis und Fehler eines Felds gemeinsam für aria-describedby
    $beschrieben = fn (string $feld, bool $hinweis = true) => implode(' ', array_filter([$hinweis ? $feld.'-hinweis' : null, $errors->has($feld) ? $feld.'-fehler' : null]));
    // Speichern abgebrochen, weil inzwischen jemand anderes gespeichert hat: der gespeicherte Stand steht neben
    // der eigenen Fassung, damit das nächste Speichern (mit frischem Stand) nichts Fremdes ungesehen überschreibt.
    $konflikt = ! $neu && is_string(old('stand')) && old('stand') !== '' && old('stand') !== (string) $modul->aktualisiert_am?->getTimestamp();
    // Zeilenenden vereinheitlichen: Browser senden Textfelder mit \r\n, gespeichert ist \n
    $text = fn ($wert) => trim(preg_replace('/\R/u', "\n", (string) $wert));
    $gespeichert = ! $konflikt ? [] : array_filter([
        'modul_nummer' => [__('Modulnummer'), (string) $modul->modul_nummer],
        'titel' => [__('Titel'), (string) $modul->titel],
        'version' => [__('Katalogversion'), (string) $modul->version],
        'link' => [__('Verweis'), (string) $modul->link],
        'beschreibung' => [__('Beschreibung'), (string) $modul->beschreibung],
        // Leer gelassene Ziele ändern nichts: dann gibt es auch nichts zu vergleichen.
        'handlungsziele' => [__('Handlungsziele und Handlungskompetenzen'), $text(old('handlungsziele')) === '' ? null : $ziele],
        // Die Modulnummer wird beim Speichern gross geschrieben, nur sie vergleicht ohne Gross-/Kleinschreibung
    ], fn ($z, $feld) => $z[1] !== null && ($feld === 'modul_nummer' ? mb_strtoupper($text(old($feld))) !== $text($z[1]) : $text(old($feld)) !== $text($z[1])), ARRAY_FILTER_USE_BOTH);
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
                    <p class="mt-2 px-1 text-xs text-muted">{{ __('Was du hier ergänzt, steht sofort allen zur Verfügung.') }}</p>
                </section>

                @if($gespeichert)
                    <section>
                        <h2 id="gespeichert-titel" class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Inzwischen gespeichert') }}</h2>
                        <div class="np-karte np-gruppe">
                            @foreach($gespeichert as $feld => [$bezeichnung, $wert])
                                <x-einstellung :label="$bezeichnung" :fuer="$feld.'-gespeichert'" :gestapelt="in_array($feld, ['beschreibung', 'handlungsziele'], true)">
                                    @if(in_array($feld, ['beschreibung', 'handlungsziele'], true))
                                        <textarea id="{{ $feld }}-gespeichert" rows="{{ $feld === 'handlungsziele' ? 8 : 3 }}" readonly aria-describedby="gespeichert-titel" class="np-feld">{{ $wert }}</textarea>
                                    @else
                                        <input type="text" id="{{ $feld }}-gespeichert" value="{{ $wert }}" readonly aria-describedby="gespeichert-titel"
                                               class="np-feld {{ match ($feld) { 'version' => 'w-24 text-right tabular-nums', 'modul_nummer' => 'w-32 tabular-nums', default => 'w-80' } }}">
                                    @endif
                                </x-einstellung>
                            @endforeach
                        </div>
                    </section>
                @endif

                <x-formular-aktionen :abbrechen="$neu ? route('modules.index') : route('modules.show', $modul->modul_id)">{{ $neu ? __('Modul anlegen') : __('Änderungen speichern') }}</x-formular-aktionen>
            </form>
        </div>
    </div>
</x-app-layout>
