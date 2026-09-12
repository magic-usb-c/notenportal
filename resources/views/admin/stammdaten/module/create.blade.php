<x-app-layout>
    <x-slot name="title">{{ __('Neues Modul') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Neues Modul')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.modules.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    {{ __('Zurück') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card p-6">
                <form method="POST" action="{{ route('admin.master-data.modules.store') }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="modul_nummer" class="text-sm font-medium text-text">{{ __('Modulnummer') }} * <span class="text-xs font-normal">({{ __('z.B. M100') }})</span></label>
                        <input type="text" id="modul_nummer" name="modul_nummer" value="{{ old('modul_nummer') }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('modul_nummer') border-note-ungenuegend @enderror">
                        @error('modul_nummer')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="titel" class="text-sm font-medium text-text">{{ __('Titel') }} *</label>
                        <input type="text" id="titel" name="titel" value="{{ old('titel') }}" required maxlength="255"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('titel') border-note-ungenuegend @enderror">
                        @error('titel')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="version" class="text-sm font-medium text-text">{{ __('Katalogversion') }}
                            <span class="text-xs font-normal">({{ __('optional, z.B. 1 – erzeugt den Verweis auf den Modulbaukasten') }})</span></label>
                        <input type="text" id="version" name="version" value="{{ old('version') }}" inputmode="numeric" maxlength="2"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('version') border-note-ungenuegend @enderror">
                        @error('version')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="beschreibung" class="text-sm font-medium text-text">{{ __('Beschreibung') }} <span class="text-xs font-normal">({{ __('optional') }})</span></label>
                        <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000"
                                  class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('beschreibung') border-note-ungenuegend @enderror">{{ old('beschreibung') }}</textarea>
                        @error('beschreibung')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ziel_gewicht_summe_default" class="text-sm font-medium text-text">{{ __('Ziel-Gewicht-Summe') }} <span class="text-xs font-normal">({{ __('Standard: 100') }})</span></label>
                        <input type="number" id="ziel_gewicht_summe_default" name="ziel_gewicht_summe_default" value="{{ old('ziel_gewicht_summe_default', 100) }}"
                               step="0.01" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('ziel_gewicht_summe_default') border-note-ungenuegend @enderror">
                        @error('ziel_gewicht_summe_default')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ __('Modul anlegen') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
