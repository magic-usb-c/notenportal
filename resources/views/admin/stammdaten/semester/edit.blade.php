<x-app-layout>
    <x-slot name="title">Semester bearbeiten</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Semester bearbeiten" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.semesters.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    Zurück
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card p-6">
                <form method="POST" action="{{ route('admin.master-data.semesters.update', $semester->semester_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="bezeichnung" class="text-xs uppercase tracking-widest text-muted font-medium">Bezeichnung *</label>
                        <input type="text" id="bezeichnung" name="bezeichnung" value="{{ old('bezeichnung', $semester->bezeichnung) }}" required maxlength="20"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('bezeichnung') border-note-ungenuegend @enderror">
                        @error('bezeichnung')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_datum" class="text-xs uppercase tracking-widest text-muted font-medium">Von *</label>
                            <input type="date" id="start_datum" name="start_datum" value="{{ old('start_datum', $semester->start_datum) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('start_datum') border-note-ungenuegend @enderror">
                            @error('start_datum')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="end_datum" class="text-xs uppercase tracking-widest text-muted font-medium">Bis *</label>
                            <input type="date" id="end_datum" name="end_datum" value="{{ old('end_datum', $semester->end_datum) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('end_datum') border-note-ungenuegend @enderror">
                            @error('end_datum')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="sortierung" class="text-xs uppercase tracking-widest text-muted font-medium">Sortierung *</label>
                        <input type="number" id="sortierung" name="sortierung" value="{{ old('sortierung', $semester->sortierung) }}" required min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('sortierung') border-note-ungenuegend @enderror">
                        @error('sortierung')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
