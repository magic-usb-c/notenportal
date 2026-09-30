<x-app-layout>
    <x-slot name="title">{{ __('Modul bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.modules.index')" :titel="__('Modul bearbeiten')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ route('admin.master-data.modules.update', $modul->modul_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="modul_nummer" class="text-sm font-medium text-text">{{ __('Modulnummer') }} *</label>
                        <input type="text" id="modul_nummer" name="modul_nummer" value="{{ old('modul_nummer', $modul->modul_nummer) }}" required maxlength="50"
                               class="np-feld mt-1 tabular-nums @error('modul_nummer') border-note-ungenuegend @enderror">
                        @error('modul_nummer')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="titel" class="text-sm font-medium text-text">{{ __('Titel') }} *</label>
                        <input type="text" id="titel" name="titel" value="{{ old('titel', $modul->titel) }}" required maxlength="255"
                               class="np-feld mt-1 @error('titel') border-note-ungenuegend @enderror">
                        @error('titel')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="version" class="text-sm font-medium text-text">{{ __('Katalogversion') }}
                            <span class="text-xs font-normal">({{ __('optional, nur für Module aus dem Modulbaukasten') }})</span></label>
                        <input type="text" id="version" name="version" value="{{ old('version', $modul->version) }}" inputmode="numeric" maxlength="2"
                               class="np-feld mt-1 tabular-nums @error('version') border-note-ungenuegend @enderror">
                        @error('version')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                        @php($mbk = \App\Support\Modulbaukasten::modulLink($modul->modul_nummer, $modul->version))
                        <p class="mt-1 text-xs text-muted">
                            @if($mbk)
                                <a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer" class="text-accent-text underline underline-offset-2">{{ __('Im Modulbaukasten öffnen') }}</a>
                            @else
                                {{ __('Ohne Version gibt es keinen Verweis auf den Modulbaukasten.') }}
                            @endif
                        </p>
                    </div>

                    <div>
                        <label for="beschreibung" class="text-sm font-medium text-text">{{ __('Beschreibung') }} <span class="text-xs font-normal">({{ __('optional') }})</span></label>
                        <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000"
                                  class="np-feld mt-1 @error('beschreibung') border-note-ungenuegend @enderror">{{ old('beschreibung', $modul->beschreibung) }}</textarea>
                        @error('beschreibung')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ziel_gewicht_summe_default" class="text-sm font-medium text-text">{{ __('Ziel-Gewicht-Summe') }}</label>
                        <input type="number" id="ziel_gewicht_summe_default" name="ziel_gewicht_summe_default"
                               value="{{ old('ziel_gewicht_summe_default', $modul->ziel_gewicht_summe_default) }}"
                               step="0.01" min="0"
                               class="np-feld mt-1 @error('ziel_gewicht_summe_default') border-note-ungenuegend @enderror">
                        @error('ziel_gewicht_summe_default')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $modul->aktiv))
                               class="np-schalter">
                        <label for="aktiv" class="text-sm text-text">{{ __('Modul aktiv') }}</label>
                    </div>

                    <x-formular-aktionen :abbrechen="route('admin.master-data.modules.index')">{{ __('Änderungen speichern') }}</x-formular-aktionen>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
