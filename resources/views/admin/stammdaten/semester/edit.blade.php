<x-app-layout>
    <x-slot name="title">{{ __('Semester bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.semesters.index')" :titel="__('Semester bearbeiten')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
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

                    <x-formular-aktionen :abbrechen="route('admin.master-data.semesters.index')">{{ __('Änderungen speichern') }}</x-formular-aktionen>
                </form>
            </div>

            <div class="np-karte mt-6 max-w-3xl p-6">
                @if($belegt)
                    <p class="text-sm text-muted">{{ __('Das Semester enthält Noten, Tracks oder Dokumente und kann nicht gelöscht werden.') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.master-data.semesters.destroy', $semester->semester_id) }}"
                          data-bestaetigen="{{ __('Semester :bezeichnung löschen?', ['bezeichnung' => $semester->bezeichnung]) }}" data-bestaetigen-knopf="{{ __('Löschen') }}"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        @method('DELETE')
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-gefahr">{{ __('Semester löschen') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
