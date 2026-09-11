<x-app-layout>
    <x-slot name="title">{{ __('Notenkategorie bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Notenkategorie bearbeiten')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.categories.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    {{ __('Zurück') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card p-6">

                <form method="POST"
                      action="{{ route('admin.master-data.categories.update', $kategorie->kategorie_id) }}"
                      class="space-y-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="code" class="text-sm font-medium text-text">{{ __('Code *') }}</label>
                            <input type="text" id="code" name="code" value="{{ old('code', $kategorie->code) }}" required maxlength="30"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('code') border-note-ungenuegend @enderror">
                            @error('code')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="name" class="text-sm font-medium text-text">{{ __('Name *') }}</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $kategorie->name) }}" required maxlength="50"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-note-ungenuegend @enderror">
                            @error('name')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="w-32">
                        <label for="sortierung" class="text-sm font-medium text-text">{{ __('Sortierung') }}</label>
                        <input type="number" id="sortierung" name="sortierung" value="{{ old('sortierung', $kategorie->sortierung) }}" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="rundung_element" class="text-sm font-medium text-text">{{ __('Rundung Zeugnisnote *') }}</label>
                            <select id="rundung_element" name="rundung_element" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('rundung_element') border-note-ungenuegend @enderror">
                                <option value="0" @selected((float) old('rundung_element', $kategorie->rundung_element) === 0.0)>{{ __('Ungerundet') }}</option>
                                <option value="0.1" @selected((float) old('rundung_element', $kategorie->rundung_element) === 0.1)>0.1</option>
                                <option value="0.25" @selected((float) old('rundung_element', $kategorie->rundung_element) === 0.25)>0.25</option>
                                <option value="0.5" @selected((float) old('rundung_element', $kategorie->rundung_element) === 0.5)>0.5</option>
                                <option value="1" @selected((float) old('rundung_element', $kategorie->rundung_element) === 1.0)>1</option>
                            </select>
                            @error('rundung_element')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="rundung_schnitt" class="text-sm font-medium text-text">{{ __('Rundung Schnitt *') }}</label>
                            <select id="rundung_schnitt" name="rundung_schnitt" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('rundung_schnitt') border-note-ungenuegend @enderror">
                                <option value="0" @selected((float) old('rundung_schnitt', $kategorie->rundung_schnitt) === 0.0)>{{ __('Ungerundet') }}</option>
                                <option value="0.1" @selected((float) old('rundung_schnitt', $kategorie->rundung_schnitt) === 0.1)>0.1</option>
                                <option value="0.25" @selected((float) old('rundung_schnitt', $kategorie->rundung_schnitt) === 0.25)>0.25</option>
                                <option value="0.5" @selected((float) old('rundung_schnitt', $kategorie->rundung_schnitt) === 0.5)>0.5</option>
                                <option value="1" @selected((float) old('rundung_schnitt', $kategorie->rundung_schnitt) === 1.0)>1</option>
                            </select>
                            @error('rundung_schnitt')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gewicht_gesamt" class="text-sm font-medium text-text">{{ __('Gewicht im Gesamtschnitt *') }}</label>
                            <input type="number" id="gewicht_gesamt" name="gewicht_gesamt" value="{{ old('gewicht_gesamt', $kategorie->gewicht_gesamt) }}" required min="0" step="0.25"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('gewicht_gesamt') border-note-ungenuegend @enderror">
                            @error('gewicht_gesamt')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="border-t border-border pt-4 space-y-4">
                        <h3 class="font-semibold text-text text-sm">{{ __('Promotion') }}</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="promotion_min_schnitt" class="text-sm font-medium text-text">{{ __('Mindestschnitt') }}</label>
                                <input type="number" id="promotion_min_schnitt" name="promotion_min_schnitt" value="{{ old('promotion_min_schnitt', $kategorie->promotion_min_schnitt) }}" min="1" max="6" step="0.1"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('promotion_min_schnitt') border-note-ungenuegend @enderror">
                                @error('promotion_min_schnitt')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="promotion_max_ungenuegend" class="text-sm font-medium text-text">{{ __('Max. ungenügende Noten') }}</label>
                                <input type="number" id="promotion_max_ungenuegend" name="promotion_max_ungenuegend" value="{{ old('promotion_max_ungenuegend', $kategorie->promotion_max_ungenuegend) }}" min="0" max="20" step="1"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('promotion_max_ungenuegend') border-note-ungenuegend @enderror">
                                @error('promotion_max_ungenuegend')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="promotion_max_minuspunkte" class="text-sm font-medium text-text">{{ __('Max. Minuspunkte') }}</label>
                                <input type="number" id="promotion_max_minuspunkte" name="promotion_max_minuspunkte" value="{{ old('promotion_max_minuspunkte', $kategorie->promotion_max_minuspunkte) }}" min="0" max="20" step="0.5"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('promotion_max_minuspunkte') border-note-ungenuegend @enderror">
                                @error('promotion_max_minuspunkte')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" name="aktiv" value="1" id="aktiv"
                               @checked(old('aktiv', $kategorie->aktiv))
                               class="rounded-sm border-border text-accent focus:ring-ring">
                        <label for="aktiv" class="text-sm text-text">{{ __('Aktiv') }}</label>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="submit" :disabled="loading"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ __('Speichern') }}
                        </button>
                        <a href="{{ route('admin.master-data.categories.index') }}"
                           class="px-4 py-2 h-10 rounded-xl glass-btn text-text">
                            {{ __('Abbrechen') }}
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
