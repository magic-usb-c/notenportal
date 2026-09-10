<x-app-layout>
    <x-slot name="title">Neue Notenkategorie</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neue Notenkategorie</h2>
            <a href="{{ route('admin.stammdaten.kategorien.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.stammdaten.kategorien.store') }}" class="space-y-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="code" class="text-xs uppercase tracking-widest text-muted font-medium">Code * <span class="text-xs font-normal">(max. 30 Zeichen)</span></label>
                            <input type="text" id="code" name="code" value="{{ old('code') }}" required maxlength="30"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('code') border-red-400 @enderror">
                            @error('code')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="name" class="text-xs uppercase tracking-widest text-muted font-medium">Name * <span class="text-xs font-normal">(max. 50 Zeichen)</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="50"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                            @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="w-32">
                        <label for="sortierung" class="text-xs uppercase tracking-widest text-muted font-medium">Sortierung</label>
                        <input type="number" id="sortierung" name="sortierung" value="{{ old('sortierung') }}" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="rundung_element" class="text-xs uppercase tracking-widest text-muted font-medium">Rundung Zeugnisnote *</label>
                            <select id="rundung_element" name="rundung_element" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('rundung_element') border-red-400 @enderror">
                                <option value="0" @selected((float) old('rundung_element', 0.5) === 0.0)>Ungerundet</option>
                                <option value="0.1" @selected((float) old('rundung_element', 0.5) === 0.1)>0.1</option>
                                <option value="0.25" @selected((float) old('rundung_element', 0.5) === 0.25)>0.25</option>
                                <option value="0.5" @selected((float) old('rundung_element', 0.5) === 0.5)>0.5</option>
                                <option value="1" @selected((float) old('rundung_element', 0.5) === 1.0)>1</option>
                            </select>
                            @error('rundung_element')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="rundung_schnitt" class="text-xs uppercase tracking-widest text-muted font-medium">Rundung Schnitt *</label>
                            <select id="rundung_schnitt" name="rundung_schnitt" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('rundung_schnitt') border-red-400 @enderror">
                                <option value="0" @selected((float) old('rundung_schnitt', 0.1) === 0.0)>Ungerundet</option>
                                <option value="0.1" @selected((float) old('rundung_schnitt', 0.1) === 0.1)>0.1</option>
                                <option value="0.25" @selected((float) old('rundung_schnitt', 0.1) === 0.25)>0.25</option>
                                <option value="0.5" @selected((float) old('rundung_schnitt', 0.1) === 0.5)>0.5</option>
                                <option value="1" @selected((float) old('rundung_schnitt', 0.1) === 1.0)>1</option>
                            </select>
                            @error('rundung_schnitt')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gewicht_gesamt" class="text-xs uppercase tracking-widest text-muted font-medium">Gewicht im Gesamtschnitt *</label>
                            <input type="number" id="gewicht_gesamt" name="gewicht_gesamt" value="{{ old('gewicht_gesamt', '1') }}" required min="0" step="0.25"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('gewicht_gesamt') border-red-400 @enderror">
                            @error('gewicht_gesamt')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="border-t border-border pt-4 space-y-4">
                        <h3 class="font-semibold text-text text-sm">Promotion</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="promotion_min_schnitt" class="text-xs uppercase tracking-widest text-muted font-medium">Mindestschnitt</label>
                                <input type="number" id="promotion_min_schnitt" name="promotion_min_schnitt" value="{{ old('promotion_min_schnitt') }}" min="1" max="6" step="0.1"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('promotion_min_schnitt') border-red-400 @enderror">
                                @error('promotion_min_schnitt')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="promotion_max_ungenuegend" class="text-xs uppercase tracking-widest text-muted font-medium">Max. ungenügende Noten</label>
                                <input type="number" id="promotion_max_ungenuegend" name="promotion_max_ungenuegend" value="{{ old('promotion_max_ungenuegend') }}" min="0" max="20" step="1"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('promotion_max_ungenuegend') border-red-400 @enderror">
                                @error('promotion_max_ungenuegend')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="promotion_max_minuspunkte" class="text-xs uppercase tracking-widest text-muted font-medium">Max. Minuspunkte</label>
                                <input type="number" id="promotion_max_minuspunkte" name="promotion_max_minuspunkte" value="{{ old('promotion_max_minuspunkte') }}" min="0" max="20" step="0.5"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('promotion_max_minuspunkte') border-red-400 @enderror">
                                @error('promotion_max_minuspunkte')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="submit" :disabled="loading"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                            Speichern
                        </button>
                        <a href="{{ route('admin.stammdaten.kategorien.index') }}"
                           class="px-4 py-2 h-10 rounded-xl glass-btn text-text">
                            Abbrechen
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
