<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Modul bearbeiten</h2>
            <a href="{{ route('admin.stammdaten.module.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-card border border-border rounded-2xl shadow-sm p-6">
                <form method="POST" action="{{ route('admin.stammdaten.module.update', $modul->modul_id) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="text-sm font-medium text-muted">Modulnummer *</label>
                        <input type="text" name="modul_nummer" value="{{ old('modul_nummer', $modul->modul_nummer) }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('modul_nummer') border-red-400 @enderror">
                        @error('modul_nummer')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Titel *</label>
                        <input type="text" name="titel" value="{{ old('titel', $modul->titel) }}" required maxlength="255"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('titel') border-red-400 @enderror">
                        @error('titel')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Beschreibung <span class="text-xs font-normal">(optional)</span></label>
                        <textarea name="beschreibung" rows="3" maxlength="2000"
                                  class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('beschreibung') border-red-400 @enderror">{{ old('beschreibung', $modul->beschreibung) }}</textarea>
                        @error('beschreibung')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Ziel-Gewicht-Summe</label>
                        <input type="number" name="ziel_gewicht_summe_default"
                               value="{{ old('ziel_gewicht_summe_default', $modul->ziel_gewicht_summe_default) }}"
                               step="0.01" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('ziel_gewicht_summe_default') border-red-400 @enderror">
                        @error('ziel_gewicht_summe_default')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $modul->aktiv))
                               class="rounded border-border text-accent focus:ring-ring">
                        <label for="aktiv" class="text-sm text-text">Modul aktiv</label>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 font-medium">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
