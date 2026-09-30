<x-app-layout>
    <x-slot name="title">{{ __('Semester bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.semesters.index')" :titel="__('Semester bearbeiten')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ route('admin.master-data.semesters.update', $semester->semester_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="bezeichnung" class="text-sm font-medium text-text">{{ __('Bezeichnung') }} *</label>
                        <input type="text" id="bezeichnung" name="bezeichnung" value="{{ old('bezeichnung', $semester->bezeichnung) }}" required maxlength="20"
                               class="np-feld mt-1 @error('bezeichnung') border-note-ungenuegend @enderror">
                        @error('bezeichnung')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_datum" class="text-sm font-medium text-text">{{ __('Von') }} *</label>
                            <input type="date" id="start_datum" name="start_datum" value="{{ old('start_datum', $semester->start_datum) }}" required
                                   class="np-feld mt-1 @error('start_datum') border-note-ungenuegend @enderror">
                            @error('start_datum')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="end_datum" class="text-sm font-medium text-text">{{ __('Bis') }} *</label>
                            <input type="date" id="end_datum" name="end_datum" value="{{ old('end_datum', $semester->end_datum) }}" required
                                   class="np-feld mt-1 @error('end_datum') border-note-ungenuegend @enderror">
                            @error('end_datum')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="sortierung" class="text-sm font-medium text-text">{{ __('Sortierung') }} *</label>
                        <input type="number" id="sortierung" name="sortierung" value="{{ old('sortierung', $semester->sortierung) }}" required min="0"
                               class="np-feld mt-1 @error('sortierung') border-note-ungenuegend @enderror">
                        @error('sortierung')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="np-knopf np-knopf-primaer np-knopf-gross w-full">
                            {{ __('Änderungen speichern') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
