<x-app-layout>
    <x-slot name="title">Semester bearbeiten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Semester bearbeiten</h2>
            <a href="{{ route('admin.stammdaten.semester.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.stammdaten.semester.update', $semester->semester_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="bezeichnung" class="text-sm font-medium text-muted">Bezeichnung *</label>
                        <input type="text" id="bezeichnung" name="bezeichnung" value="{{ old('bezeichnung', $semester->bezeichnung) }}" required maxlength="20"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('bezeichnung') border-red-400 @enderror">
                        @error('bezeichnung')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_datum" class="text-sm font-medium text-muted">Von *</label>
                            <input type="date" id="start_datum" name="start_datum" value="{{ old('start_datum', $semester->start_datum) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('start_datum') border-red-400 @enderror">
                            @error('start_datum')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="end_datum" class="text-sm font-medium text-muted">Bis *</label>
                            <input type="date" id="end_datum" name="end_datum" value="{{ old('end_datum', $semester->end_datum) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('end_datum') border-red-400 @enderror">
                            @error('end_datum')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="sortierung" class="text-sm font-medium text-muted">Sortierung *</label>
                        <input type="number" id="sortierung" name="sortierung" value="{{ old('sortierung', $semester->sortierung) }}" required min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('sortierung') border-red-400 @enderror">
                        @error('sortierung')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
