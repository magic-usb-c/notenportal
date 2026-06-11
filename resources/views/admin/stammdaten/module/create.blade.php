<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neues Modul</h2>
            <a href="{{ route('admin.stammdaten.module.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.stammdaten.module.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="text-sm font-medium text-muted">Modulnummer * <span class="text-xs font-normal">(z.B. M100)</span></label>
                        <input type="text" name="modul_nummer" value="{{ old('modul_nummer') }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('modul_nummer') border-red-400 @enderror">
                        @error('modul_nummer')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Titel *</label>
                        <input type="text" name="titel" value="{{ old('titel') }}" required maxlength="255"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('titel') border-red-400 @enderror">
                        @error('titel')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Beschreibung <span class="text-xs font-normal">(optional)</span></label>
                        <textarea name="beschreibung" rows="3" maxlength="2000"
                                  class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('beschreibung') border-red-400 @enderror">{{ old('beschreibung') }}</textarea>
                        @error('beschreibung')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Ziel-Gewicht-Summe <span class="text-xs font-normal">(Standard: 100)</span></label>
                        <input type="number" name="ziel_gewicht_summe_default" value="{{ old('ziel_gewicht_summe_default', 100) }}"
                               step="0.01" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('ziel_gewicht_summe_default') border-red-400 @enderror">
                        @error('ziel_gewicht_summe_default')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium">
                            Modul anlegen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
